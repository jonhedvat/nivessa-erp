<?php

namespace App\Http\Controllers;

use App\Category;
use GuzzleHttp\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Sarah 2026-11-15: "Price it for me" on Mass Add → Bulk Product Entry.
 * Staff type a rough list ("Rumours LP VG+, Thriller sealed") and Claude
 * returns JSON that we turn into the pipe lines the bulk parser already reads
 * (Name | Artist | Category | Subcategory | SKU | Price | Bin | Location | Grade) — the same thing
 * staff used to get by pasting a prompt into ChatGPT by hand.
 *
 * Uses the same Anthropic key as the "Ask the ERP" help assistant.
 */
class MassAddAiPriceController extends Controller
{
    const ANTHROPIC_URL = 'https://api.anthropic.com/v1/messages';
    const MODEL = 'claude-sonnet-5-5';

    public function price(Request $request)
    {
        if (!auth()->user() || !auth()->user()->can('product.create')) {
            return response()->json(['ok' => false, 'error' => 'Not allowed.'], 403);
        }

        $items = trim((string) $request->input('items', ''));
        if ($items === '') {
            return response()->json(['ok' => false, 'error' => 'Type some items first.']);
        }
        $items = mb_substr($items, 0, 12000);

        $cfg = HelpAssistantSettingsController::readConfig();
        $apiKey = ($cfg['api_key'] ?? null) ?: config('services.anthropic.api_key') ?: env('ANTHROPIC_API_KEY');
        if (empty($apiKey)) {
            return response()->json(['ok' => false, 'error' => 'The AI key is not set up. A manager can add it at /admin/help-assistant.']);
        }
        $fallbackModel = $cfg['model'] ?? config('services.anthropic.model', 'claude-haiku-4-5');

        $businessId = $request->session()->get('user.business_id');
        $combos = collect(Category::flattenedProductCategoryCombos($businessId) ?? []);
        $categoryList = $combos
            ->groupBy(function ($c) { return explode(' > ', $c['label'], 2)[0]; })
            ->map(function ($g, $cat) {
                $subs = $g->map(function ($c) { return explode(' > ', $c['label'], 2)[1] ?? null; })->filter()->implode('; ');
                return $cat . ($subs !== '' ? ': ' . $subs : '');
            })
            ->implode("\n");

        $cardStats = $this->cardStats($businessId);
        // Case-insensitive lookup of our sold-price range for "Category > Subcategory".
        $statsLower = array_change_key_case($cardStats, CASE_LOWER);
        $statFor = function ($cat, $sub) use ($statsLower) {
            return $statsLower[mb_strtolower($cat . ($sub !== '' ? ' > ' . $sub : ''))] ?? null;
        };

        $system = "You price used and new items for Nivessa, a retail record store with shops on Pico Blvd and in Hollywood, Los Angeles. "
            . "Staff type a rough list of items they are adding to inventory. For each item, work out the product name and artist, "
            . "pick the best category and subcategory, and give a fair in-store retail price based on current market value (Discogs / eBay sold prices) for the format and condition given. "
            . "If no condition is given, assume used in good condition (VG+). If no format is given, assume LP vinyl. "
            . "Sealed items are new stock: price them at what a record store charges for a new copy today, not at a used or online-discount price.\n"
            . "Staff may write a note or instruction instead of an item (for example 'here are a few DVDs I want to price' or 'all of these are sealed'). "
            . "Do not output those lines as items; apply them to the items they describe (format, condition, category).\n\n"
            . "Call the price_items tool once with every item, each exactly once. price is a plain number. "
            . "condition is the grade ONLY if staff wrote one (Sealed, M, NM, VG+, VG, G+, G, F, P), otherwise an empty string. "
            . "If something is not music (a toy, a book, a card), use the closest category from the list.\n\n"
            . "Copy Category and Subcategory exactly from this list (Category: Subcategories). LPs / albums go in Vinyl - Used or Vinyl - Sealed, not 7\", 45 RPM:\n"
            . $categoryList
            . $this->cardPriceGuide($cardStats);

        $call = function ($model) use ($apiKey, $system, $items) {
            $client = new Client(['timeout' => 90]);
            return $client->post(self::ANTHROPIC_URL, [
                'headers' => [
                    'x-api-key' => $apiKey,
                    'anthropic-version' => '2023-06-01',
                    'content-type' => 'application/json',
                ],
                'json' => [
                    'model' => $model,
                    'max_tokens' => 8000,
                    'system' => $system,
                    'tools' => [[
                        'name' => 'price_items',
                        'description' => 'Record the priced items.',
                        'input_schema' => [
                            'type' => 'object',
                            'properties' => [
                                'items' => [
                                    'type' => 'array',
                                    'items' => [
                                        'type' => 'object',
                                        'properties' => [
                                            'name' => ['type' => 'string'],
                                            'artist' => ['type' => 'string'],
                                            'category' => ['type' => 'string'],
                                            'subcategory' => ['type' => 'string'],
                                            'price' => ['type' => 'number'],
                                            'condition' => ['type' => 'string'],
                                            'premium' => [
                                                'type' => 'boolean',
                                                'description' => 'Trading cards only: true if the card is graded / slabbed, a true rookie card, autographed, a scarce insert or parallel, or a sealed box. false for ordinary raw cards and for everything that is not a card.',
                                            ],
                                        ],
                                        'required' => ['name', 'artist', 'category', 'subcategory', 'price'],
                                    ],
                                ],
                            ],
                            'required' => ['items'],
                        ],
                    ]],
                    'tool_choice' => ['type' => 'tool', 'name' => 'price_items'],
                    'messages' => [['role' => 'user', 'content' => "Items:\n" . $items]],
                ],
                'http_errors' => false,
            ]);
        };

        try {
            $response = $call(self::MODEL);
            // If the account can't use the pricing model, fall back to the help-assistant model.
            if (in_array($response->getStatusCode(), [400, 403, 404], true)) {
                $body = (string) $response->getBody();
                if (stripos($body, 'model') !== false) {
                    $response = $call($fallbackModel);
                }
            }

            $status = $response->getStatusCode();
            $data = json_decode((string) $response->getBody(), true);
            if ($status !== 200) {
                $msg = $data['error']['message'] ?? '';
                \Log::warning('MassAddAiPrice Anthropic error', ['status' => $status, 'message' => $msg]);
                if ($status === 401) {
                    return response()->json(['ok' => false, 'error' => 'The AI key was rejected. A manager needs to update it at /admin/help-assistant.']);
                }
                if (stripos($msg, 'credit') !== false) {
                    return response()->json(['ok' => false, 'error' => 'The AI account is out of credit.']);
                }
                return response()->json(['ok' => false, 'error' => 'The AI service had an error. Try again in a minute.']);
            }

            $rows = null;
            foreach (($data['content'] ?? []) as $block) {
                if (($block['type'] ?? '') === 'tool_use' && isset($block['input']['items'])) {
                    $rows = $block['input']['items'];
                }
            }
            if (!is_array($rows) || empty($rows)) {
                \Log::warning('MassAddAiPrice unreadable answer', ['content' => $data['content'] ?? null]);
                return response()->json(['ok' => false, 'error' => "Couldn't read the AI's answer. Try again, or list fewer items."]);
            }

            // Sarah 2026-11-15: check OUR sales history so the AI never underprices
            // something we already sell for more, or something that flies off the shelf.
            // Output is pipe-separated: some subcategories contain commas ("R&B, Soul & Funk").
            $clean = function ($v) { return trim(str_replace(['|', "\n", "\r"], ' ', (string) $v)); };
            $lines = [];
            $notes = [];
            foreach ($rows as $r) {
                if (!is_array($r)) continue;
                $name = $clean($r['name'] ?? '');
                if ($name === '') continue;
                $artist = $clean($r['artist'] ?? '');
                $cat = $clean($r['category'] ?? '');
                $sub = $clean($r['subcategory'] ?? '');
                $cond = $clean($r['condition'] ?? '');
                $ai = round((float) preg_replace('/[^0-9.]/', '', (string) ($r['price'] ?? '')), 2);
                $price = $ai;

                $h = $this->storeHistory($businessId, $name, $artist, $cat);
                $floor = $h ? ($h['fast'] ? $h['p75'] : $h['median']) : 0;
                $histNote = function ($price) use ($h, $name, $artist, $ai) {
                    return ['item' => $name . ($artist !== '' ? ' - ' . $artist : ''),
                        'ai' => $ai, 'price' => $price, 'sales' => $h['sales'], 'median' => $h['median'],
                        'days' => $h['days'], 'fast' => $h['fast'], 'in_stock' => $h['in_stock'], 'match' => $h['match'][0] ?? ''];
                };
                if ($h && $floor > $ai) {
                    // Sarah 2026-11-16: "if you see things selling, why let someone price
                    // it lower by accident" - we sold this same item for more: use our price.
                    $price = $floor;
                    $notes[] = $histNote($price);
                } elseif (stripos($cat, 'Trading Card') === 0) {
                    // Ordinary raw card we have no exact history for: the AI's online
                    // price runs well above walk-in prices, so meet in the middle
                    // with what this kind of card usually sells for here.
                    $cs = empty($r['premium']) ? $statFor($cat, $sub) : null;
                    if ($cs && $ai > $cs['median']) {
                        $price = (float) round(min(($ai + $cs['median']) / 2, max($cs['p90'], $cs['median'])));
                        $notes[] = ['item' => $name, 'card' => true, 'ai' => $ai, 'price' => $price,
                            'sales' => $cs['sales'], 'median' => $cs['median'], 'kind' => $sub !== '' ? $sub : $cat];
                    }
                } elseif (!$h && $ai > 0 && stripos($cat, '7"') !== 0 && !in_array(strtoupper($cond), ['G+', 'G', 'F', 'P'], true)) {
                    // Sarah 2026-11-16: the AI runs about a third low on sealed vinyl / CDs
                    // and cassettes. No exact history for this item, so never go below
                    // what three quarters of our sales in its category + genre went for.
                    // Tested on 124 sold items: sealed CDs badly low 7 -> 1 of 14, sealed
                    // vinyl 6 -> 2 of 13. 45s are left out (it made them worse), and so is
                    // anything staff graded G+ or lower.
                    $cs = $statFor($cat, $sub);
                    if ($cs && $ai < $cs['p25']) {
                        $price = (float) $cs['p25'];
                        $notes[] = ['item' => $name . ($artist !== '' ? ' - ' . $artist : ''), 'low' => true, 'ai' => $ai, 'price' => $price,
                            'sales' => $cs['sales'], 'median' => $cs['median'], 'kind' => $cat . ($sub !== '' ? ' > ' . $sub : '')];
                    }
                }
                if ($h && $price == $ai) {
                    $notes[] = $histNote($price); // we have history and the AI was not under it
                }
                // Name | Artist | Category | Subcategory | SKU | Price | Bin | Location | Media grade
                $lines[] = implode(' | ', [$name, $artist, $cat, $sub, '', $price > 0 ? number_format($price, 2, '.', '') : '', '', '', $cond]);
            }
            if (empty($lines)) {
                return response()->json(['ok' => false, 'error' => "Couldn't read the AI's answer. Try again, or list fewer items."]);
            }

            return response()->json(['ok' => true, 'csv' => implode("\n", $lines), 'count' => count($lines), 'history' => $notes]);
        } catch (\Exception $e) {
            \Log::warning('MassAddAiPrice failed', ['error' => $e->getMessage()]);
            return response()->json(['ok' => false, 'error' => "Couldn't reach the AI service. Try again in a minute."]);
        }
    }

    /**
     * Sarah 2026-11-15: the AI priced common Michael Jordan cards at $18-45 when
     * we sell them for $4-12, and priced sealed vinyl / CDs, cassettes, books and
     * 45s about a third under what we get. Single items rarely match
     * storeHistory() by exact name, so this is the fallback: what each
     * "Category > Subcategory" really sold for here in the last 2 years
     * (sales, 25th pct, median, 90th pct; whole dollars). Needs 20+ sales
     * (8+ for trading cards). Built from a per-dollar histogram so we never
     * pull every sell line into PHP.
     */
    private function cardStats($businessId)
    {
        try {
            return \Cache::remember('mass_add_ai_price_stats_v2_' . $businessId, now()->addHours(6), function () use ($businessId) {
                $rows = DB::table('transaction_sell_lines as tsl')
                    ->join('transactions as t', 't.id', '=', 'tsl.transaction_id')
                    ->join('products as p', 'p.id', '=', 'tsl.product_id')
                    ->join('categories as c', 'c.id', '=', 'p.category_id')
                    ->leftJoin('categories as sc', 'sc.id', '=', 'p.sub_category_id')
                    ->where('t.business_id', $businessId)
                    ->where('t.type', 'sell')
                    ->where('t.status', 'final')
                    ->whereNull('tsl.parent_sell_line_id')
                    ->where('t.transaction_date', '>=', now()->subYears(2))
                    ->where('tsl.unit_price_inc_tax', '>', 0)
                    ->groupBy('c.name', 'sc.name', DB::raw('GREATEST(1, ROUND(tsl.unit_price_inc_tax))'))
                    ->get(['c.name as cat', 'sc.name as sub', DB::raw('GREATEST(1, ROUND(tsl.unit_price_inc_tax)) as price'), DB::raw('COUNT(*) as n')]);
                $out = [];
                foreach ($rows->groupBy(function ($r) { return trim($r->cat) . ($r->sub ? ' > ' . trim($r->sub) : ''); }) as $label => $g) {
                    $hist = $g->map(function ($r) { return [(float) $r->price, (int) $r->n]; })->sortBy(function ($x) { return $x[0]; })->values()->all();
                    $n = array_sum(array_map(function ($x) { return $x[1]; }, $hist));
                    if ($n < (stripos($label, 'Trading Card') === 0 ? 8 : 20)) continue;
                    $pct = function ($f) use ($hist, $n) {
                        $target = (int) floor($f * ($n - 1) + 0.5);
                        $seen = 0;
                        foreach ($hist as $x) {
                            $seen += $x[1];
                            if ($seen > $target) return $x[0];
                        }
                        return $hist[count($hist) - 1][0];
                    };
                    $out[$label] = ['sales' => $n, 'p25' => $pct(0.25), 'median' => $pct(0.5), 'p90' => $pct(0.9)];
                }
                return $out;
            });
        } catch (\Exception $e) {
            \Log::warning('MassAddAiPrice card stats failed', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /** Prompt text for trading cards. We do the middle-ground math ourselves (see price()). */
    private function cardPriceGuide($cardStats)
    {
        if (empty($cardStats)) return '';
        $lines = [];
        foreach ($cardStats as $label => $cs) {
            if (stripos($label, 'Trading Card') !== 0) continue;
            $lines[] = $label . ': usually $' . round($cs['median']) . ', rarely above $' . round($cs['p90']);
        }
        if (empty($lines)) return '';
        return "\n\nTrading cards: give the realistic online SOLD price (not asking prices) for the exact card in the condition given; a crease or other damage lowers it. "
            . "Walk-in prices in our shops are lower than online; for reference, what we usually sell cards for:\n" . implode("\n", $lines) . "\n"
            . "Set premium=true only for cards that really are worth more: graded / slabbed (PSA, BGS, SGC), true rookie cards, autographs, scarce inserts or parallels, sealed boxes. "
            . "Card names: keep the player's name in the product name ('Michael Jordan 1990-91 NBA Hoops #65'), and keep any grading in the name too ('... PSA 9') "
            . "since PSA / BGS grades do not fit the condition field. Artist is the player (or the franchise, e.g. Pokemon).";
    }

    /** Lower-case word list for fuzzy title matching: no accents, punctuation or filler words. */
    private function nameTokens($text)
    {
        $t = mb_strtolower(\Illuminate\Support\Str::ascii((string) $text));
        $t = preg_replace('/[^a-z0-9]+/', ' ', $t);
        $noise = ['the', 'a', 'an', 'and', 'of', 'lp', 'vinyl', 'record', 'cd', 'dvd', 'vhs', 'cassette', 'tape', 'sealed', 'used', 'new', 'album'];
        return array_values(array_filter(explode(' ', $t), function ($w) use ($noise) {
            return $w !== '' && !in_array($w, $noise, true);
        }));
    }

    /**
     * Is this product of ours the same title as the item being priced?
     * Fuzzy on purpose - our names are written many ways ("SMITH,ELLIOTT - Roman
     * Candle", "LIMP BIZKIT / CHOCOLATE STARFISH & THE HOT DOG FLA"):
     *  - the artist's words must all appear in our name or artist field;
     *  - the title words (artist words removed) must overlap 60%+, or the title
     *    text be 85%+ alike (handles cut-off names);
     *  - numbers / volume marks must be identical ("Greatest Hits" != "Greatest Hits II").
     */
    private function sameTitle(array $qTitle, array $qArtist, $prodName, $prodArtist)
    {
        $pAll = $this->nameTokens($prodName . ' ' . $prodArtist);
        if ($qArtist && array_diff($qArtist, $pAll)) return false;
        $pTitle = array_values(array_diff($this->nameTokens($prodName), $qArtist));
        $isNum = function ($w) { return (bool) preg_match('/^(\d+|ii|iii|iv|vi|vii|viii|ix|vol\w*|pt|part|disc|season|series)$/', $w); };
        $qn = array_values(array_unique(array_filter($qTitle, $isNum))); sort($qn);
        $pn = array_values(array_unique(array_filter($pTitle, $isNum))); sort($pn);
        if ($qn !== $pn) return false;
        if (!$qTitle || !$pTitle) return !$qTitle && !$pTitle; // self-titled both sides
        $inter = count(array_intersect(array_unique($qTitle), array_unique($pTitle)));
        $union = count(array_unique(array_merge($qTitle, $pTitle)));
        if ($union > 0 && $inter / $union >= 0.6) return true;
        similar_text(implode(' ', $qTitle), implode(' ', $pTitle), $pct);
        return $pct >= 85;
    }

    /**
     * What we've actually sold this item for in the last 2 years, and how fast.
     * Match: the same title (fuzzy, see sameTitle) in the same category (CD vs
     * vinyl vs DVD price very differently). One sale is enough.
     * "fast" = copies usually sell within 30 days of being added; for fast
     * sellers the floor is the 75th-percentile sale price, otherwise the median.
     */
    private function storeHistory($businessId, $name, $artist, $category)
    {
        if (mb_strlen($name) < 2) return null;
        try {
            $qArtist = in_array(mb_strtolower(trim($artist)), ['', 'unknown', 'various', 'various artists', 'soundtrack', 'n/a'], true)
                ? [] : $this->nameTokens($artist);
            $qTitle = array_values(array_diff($this->nameTokens($name), $qArtist));
            // Narrow in SQL by the two longest words, then decide in PHP.
            $words = $qTitle ?: $qArtist;
            $words = array_values(array_filter($words, function ($w) { return strlen($w) >= 3; }));
            usort($words, function ($a, $b) { return strlen($b) - strlen($a); });
            $words = array_slice($words, 0, 2);

            $pq = DB::table('products as p')
                ->leftJoin('categories as c', 'c.id', '=', 'p.category_id')
                ->where('p.business_id', $businessId);
            if ($category !== '') {
                $pq->where('c.name', $category);
            }
            $pq->where(function ($w) use ($name, $words) {
                $w->where('p.name', $name);
                if ($words) {
                    $w->orWhere(function ($l) use ($words) {
                        foreach ($words as $word) $l->where('p.name', 'like', '%' . $word . '%');
                    });
                }
            });
            $cands = $pq->limit(600)->get(['p.id', 'p.name', 'p.artist', 'p.created_at']);
            $products = $cands->filter(function ($p) use ($name, $qTitle, $qArtist) {
                return $this->sameTitle($qTitle, $qArtist, $p->name, (string) $p->artist);
            })->take(200);
            if ($products->isEmpty()) return null;
            $created = $products->pluck('created_at', 'id');
            $names = $products->pluck('name', 'id');

            $sales = DB::table('transaction_sell_lines as tsl')
                ->join('transactions as t', 't.id', '=', 'tsl.transaction_id')
                ->whereIn('tsl.product_id', $created->keys()->all())
                ->where('t.business_id', $businessId)
                ->where('t.type', 'sell')
                ->where('t.status', 'final')
                ->whereNull('tsl.parent_sell_line_id')
                ->where('t.transaction_date', '>=', now()->subYears(2))
                ->where('tsl.unit_price_inc_tax', '>', 0)
                ->get(['tsl.product_id', 'tsl.unit_price_inc_tax as price', 't.transaction_date']);
            if ($sales->count() < 1) return null;

            $prices = $sales->pluck('price')->map(function ($p) { return (float) $p; })->sort()->values()->all();
            $n = count($prices);
            $median = $n % 2 ? $prices[intdiv($n, 2)] : ($prices[$n / 2 - 1] + $prices[$n / 2]) / 2;
            $p75 = $prices[min($n - 1, (int) floor(0.75 * ($n - 1) + 0.5))];

            // Days from the copy being added to its first sale, per product.
            $days = [];
            foreach ($sales->groupBy('product_id') as $pid => $rows) {
                $c = $created[$pid] ?? null;
                if (!$c) continue;
                $first = $rows->min('transaction_date');
                $d = (strtotime($first) - strtotime($c)) / 86400;
                if ($d >= 0) $days[] = $d;
            }
            sort($days);
            $medDays = $days ? (int) round($days[intdiv(count($days), 2)]) : null;

            $inStock = (float) DB::table('variation_location_details')
                ->whereIn('product_id', $created->keys()->all())
                ->sum('qty_available');

            $match = $sales->pluck('product_id')->unique()->map(function ($id) use ($names) { return $names[$id] ?? ''; })->filter()->unique()->take(6)->values()->all();

            return [
                'match' => $match,
                'sales' => $n,
                'median' => round($median, 2),
                'p75' => round($p75, 2),
                'days' => $medDays,
                'fast' => $medDays !== null && $medDays <= 30,
                'in_stock' => (int) $inStock,
            ];
        } catch (\Exception $e) {
            \Log::warning('MassAddAiPrice history failed', ['error' => $e->getMessage()]);
            return null;
        }
    }
}
