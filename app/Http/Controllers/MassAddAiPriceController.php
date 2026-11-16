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
    const MODEL_ROUTINE = 'claude-haiku-5-5';
    const MODEL_ESCALATE = 'claude-sonnet-5-5';
    const BATCH_SIZE = 25;
    const ESCALATE_MIN_PRICE = 30;
    // USD per million tokens, used to log cost. Confirm against Anthropic's current price list.
    const RATES = [
        'claude-haiku-5-5' => ['in' => 1.00, 'out' => 5.00],
        'claude-sonnet-5-5' => ['in' => 3.00, 'out' => 15.00],
    ];

    /** Our sales for each numbered line of the current request (set by price()). */
    private $comparables = [];

    /** Live web prices for each numbered line of the current request (set by price()). */
    private $web = [];

    /**
     * Discogs (VG+ suggested price for the best-matching release) and eBay
     * (median asking price of fixed-price listings whose titles match the line).
     * Cached 24 hours. Asking prices run above what items sell for, so the AI
     * gets both numbers and the escalation check treats a 2x gap as a conflict.
     */
    const EBAY_SOLD_SHARE = 0.8;

    private function webComparables($businessId, $line)
    {
        $key = 'ai_web_comp_v2:' . $businessId . ':' . md5(mb_strtolower($line));
        return \Cache::remember($key, 24 * 3600, function () use ($businessId, $line) {
            $out = ['discogs' => null, 'ebay' => null];
            try {
                $d = (new \App\Services\DiscogsService($businessId))->searchProductPrice($line);
                if (empty($d['error'])) {
                    foreach ((array) ($d['prices'] ?? []) as $p) {
                        if (stripos((string) ($p['condition'] ?? ''), 'Very Good Plus') === 0 || stripos((string) ($p['condition'] ?? ''), 'VG+') !== false) {
                            $out['discogs'] = (float) str_replace(',', '', (string) $p['value']);
                            break;
                        }
                    }
                }
            } catch (\Throwable $e) {
                \Log::info('MassAddAiPrice discogs lookup failed', ['error' => $e->getMessage()]);
            }
            try {
                $res = (new \App\Services\EbayService($businessId))->browseSearch([
                    'q' => mb_substr($line, 0, 100),
                    'limit' => 50,
                    'filter' => 'buyingOptions:{FIXED_PRICE},priceCurrency:USD,price:[5..]',
                ]);
                $tokens = $this->nameTokens($line);
                $need = max(2, (int) ceil(count($tokens) * 0.6));
                $prices = [];
                foreach ((array) ($res['itemSummaries'] ?? []) as $it) {
                    $title = mb_strtolower((string) ($it['title'] ?? ''));
                    $hit = 0;
                    foreach ($tokens as $t) {
                        if (mb_strpos($title, $t) !== false) $hit++;
                    }
                    $price = (float) ($it['price']['value'] ?? 0);
                    if ($price > 0 && $hit >= $need) $prices[] = $price;
                }
                if (count($prices) >= 3) {
                    sort($prices);
                    $n = count($prices);
                    $median = $n % 2 ? $prices[intdiv($n, 2)] : ($prices[$n / 2 - 1] + $prices[$n / 2]) / 2;
                    // Asking prices run above what items sell for; 80% of the asking median is our estimate of sold.
                    $out['ebay'] = ['median' => round($median * self::EBAY_SOLD_SHARE, 2), 'asking' => round($median, 2), 'n' => $n];
                }
            } catch (\Throwable $e) {
                \Log::info('MassAddAiPrice eBay lookup failed', ['error' => $e->getMessage()]);
            }
            return $out;
        });
    }

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
        $userId = auth()->id();
        $sessionId = (string) \Illuminate\Support\Str::uuid();

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

        // Constant for the whole business, so it is cached across batches and clicks.
        $system = "You price used and new items for Nivessa, a retail record store with shops on Pico Blvd and in Hollywood, Los Angeles. "
            . "Staff type a rough list of items they are adding to inventory. For each item, work out the product name and artist, "
            . "pick the best category and subcategory, and give a fair in-store retail price based on current market value (Discogs / eBay sold prices) for the format and condition given. "
            . "If no condition is given, assume used in good condition (VG+). If no format is given, assume LP vinyl. "
            . "Sealed items are new stock: price them at what a record store charges for a new copy today, not at a used or online-discount price.\n"
            . "Staff may write a note or instruction instead of an item (for example 'here are a few DVDs I want to price' or 'all of these are sealed'). "
            . "Do not output those lines as items; apply them to the items they describe (format, condition, category).\n"
            . "Some items come with 'Our sales' lines: what we actually sold that item for. Price in line with them unless they are clearly a different item. 'Web' lines are live Discogs (VG+ suggested price, based on sales) and eBay (estimated sold price, 80% of the asking median).\n\n"
            . "You must answer by calling the price_items tool once with every numbered item, each exactly once, with n set to its item number. price is a plain number. "
            . "condition is the grade ONLY if staff wrote one (Sealed, M, NM, VG+, VG, G+, G, F, P), otherwise an empty string. "
            . "confidence is 'low' if you are not sure of the exact pressing, edition or condition, otherwise 'high'. "
            . "high_value is true if the item could be worth $40 or more used. edition_unclear is true if the exact pressing or edition is hard to tell apart. "
            . "If something is not music (a toy, a book, a card), use the closest category from the list.\n\n"
            . "Copy Category and Subcategory exactly from this list (Category: Subcategories). LPs / albums go in Vinyl - Used or Vinyl - Sealed, not 7\", 45 RPM:\n"
            . $categoryList
            . $this->cardPriceGuide($cardStats);

        $callModel = function (string $model, string $userText) use ($apiKey, $system) {
            $client = new Client(['timeout' => 120]);
            $response = $client->post(self::ANTHROPIC_URL, [
                'headers' => [
                    'x-api-key' => $apiKey,
                    'anthropic-version' => '2023-06-01',
                    'content-type' => 'application/json',
                ],
                'json' => [
                    'model' => $model,
                    'max_tokens' => 8000,
                    'system' => [['type' => 'text', 'text' => $system, 'cache_control' => ['type' => 'ephemeral']]],
                    'tools' => [self::priceTool()],
                    // Sonnet 5.5 rejects a forced tool_choice ("tool" / "any" not supported), which
                    // silently fell back to Haiku 4.5. The single tool is offered; the system prompt asks for it.
                    'tool_choice' => ['type' => 'auto'],
                    'messages' => [['role' => 'user', 'content' => $userText]],
                ],
                'http_errors' => false,
            ]);
            $body = (string) $response->getBody();
            return ['status' => $response->getStatusCode(), 'body' => $body, 'data' => json_decode($body, true) ?: []];
        };

        // One pricing request for a batch of numbered lines (n => text).
        // Logs token use and cost per item.
        $ask = function (string $model, array $batch, bool $escalated) use ($callModel, $fallbackModel, $businessId, $userId, $sessionId) {
            $userText = "Items (n = item number, return every n once):\n";
            foreach ($batch as $n => $line) {
                $userText .= $n . '. ' . $line . "\n";
                $c = $this->comparables[$n] ?? null;
                if ($c && !empty($c['match'])) {
                    $userText .= '   Our sales: ' . implode('; ', array_slice($c['match'], 0, 3)) . ' - sold ' . $c['sales'] . 'x, usually $' . $c['median']
                        . ($c['fast'] ? ', sells fast' : '') . "\n";
                }
                $w = $this->web[$n] ?? null;
                if ($w && ($w['discogs'] || $w['ebay'])) {
                    $parts = [];
                    if ($w['discogs']) $parts[] = 'Discogs VG+ $' . $w['discogs'];
                    if ($w['ebay']) $parts[] = 'eBay est. sold $' . $w['ebay']['median'] . ' (80% of asking median $' . $w['ebay']['asking'] . ', ' . $w['ebay']['n'] . ' listings)';
                    $userText .= '   Web: ' . implode('; ', $parts) . "\n";
                }
            }

            $res = $callModel($model, $userText);
            $used = $model;
            if (in_array($res['status'], [400, 403, 404], true) && stripos($res['body'], 'model') !== false && $model !== $fallbackModel) {
                // Log why, so we can see when the pricing model is silently replaced.
                \Log::warning('MassAddAiPrice model fell back', ['from' => $model, 'to' => $fallbackModel, 'status' => $res['status'], 'message' => mb_substr($res['body'], 0, 300)]);
                $used = $fallbackModel;
                $res = $callModel($fallbackModel, $userText);
            }
            if ($res['status'] !== 200) {
                return ['error' => $res, 'rows' => [], 'model' => $used];
            }

            $rows = [];
            $keys = array_keys($batch);
            $pos = 0;
            foreach (($res['data']['content'] ?? []) as $block) {
                if (($block['type'] ?? '') !== 'tool_use' || !is_array($block['input']['items'] ?? null)) continue;
                foreach ($block['input']['items'] as $it) {
                    if (!is_array($it)) continue;
                    $n = isset($it['n']) && isset($batch[(int) $it['n']]) ? (int) $it['n'] : ($keys[$pos] ?? null);
                    $pos++;
                    if ($n !== null) $rows[$n] = $it;
                }
            }

            $u = $res['data']['usage'] ?? [];
            $in = (int) ($u['input_tokens'] ?? 0);
            $out = (int) ($u['output_tokens'] ?? 0);
            $cw = (int) ($u['cache_creation_input_tokens'] ?? 0);
            $cr = (int) ($u['cache_read_input_tokens'] ?? 0);
            $cost = $this->costUsd($used, $in, $out, $cw, $cr);
            $count = max(1, count($batch));
            $logRows = [];
            foreach ($batch as $n => $line) {
                $logRows[] = [
                    'business_id' => $businessId, 'user_id' => $userId, 'session_id' => $sessionId,
                    'item_no' => $n, 'item_text' => mb_substr($line, 0, 255), 'model' => $used,
                    'escalated' => $escalated ? 1 : 0, 'batch_size' => $count,
                    'input_tokens' => (int) round($in / $count), 'output_tokens' => (int) round($out / $count),
                    'cache_write_tokens' => (int) round($cw / $count), 'cache_read_tokens' => (int) round($cr / $count),
                    'cost_usd' => round($cost / $count, 6),
                    'created_at' => now(), 'updated_at' => now(),
                ];
            }
            try {
                DB::table('ai_pricing_usage')->insert($logRows);
            } catch (\Exception $e) {
                \Log::warning('MassAddAiPrice usage log failed', ['error' => $e->getMessage()]);
            }
            return ['error' => null, 'rows' => $rows, 'model' => $used];
        };

        $aiError = function (array $res) {
            $msg = $res['data']['error']['message'] ?? '';
            \Log::warning('MassAddAiPrice Anthropic error', ['status' => $res['status'], 'message' => $msg]);
            if ($res['status'] === 401) {
                return response()->json(['ok' => false, 'error' => 'The AI key was rejected. A manager needs to update it at /admin/help-assistant.']);
            }
            if (stripos($msg, 'credit') !== false) {
                return response()->json(['ok' => false, 'error' => 'The AI account is out of credit.']);
            }
            return response()->json(['ok' => false, 'error' => 'The AI service had an error. Try again in a minute.']);
        };

        try {
            $numbered = [];
            $i = 0;
            foreach (preg_split('/\r\n|\r|\n/', $items) as $l) {
                $l = trim($l);
                if ($l === '') continue;
                $numbered[++$i] = $l;
            }

            // Comparables: what we have sold that looks like each line. Retrieved
            // before the AI sees the batch, and used to decide what needs a second look.
            $this->comparables = [];
            $conflict = [];
            $this->web = [];
            foreach ($numbered as $n => $l) {
                $c = $this->storeHistory($businessId, $l, '', '');
                $this->comparables[$n] = $c;
                // Our sales for this title are spread out (e.g. a $5 copy and a $40 copy).
                $conflict[$n] = $c && $c['median'] > 0 && $c['p75'] > 2 * $c['median'];
                // Live web prices: Discogs (VG+ suggestion) and eBay (asking median).
                $w = $this->webComparables($businessId, $l);
                $this->web[$n] = $w;
                // Discogs and eBay disagree by 2x or more.
                $prices = array_filter([$w['discogs'], $w['ebay']['median'] ?? null]);
                if (count($prices) === 2 && max($prices) > 2 * min($prices)) {
                    $conflict[$n] = true;
                }
            }

            // 1. Routine pass on Haiku, batches of BATCH_SIZE lines.
            $final = [];
            foreach (array_chunk($numbered, self::BATCH_SIZE, true) as $chunk) {
                $res = $ask(self::MODEL_ROUTINE, $chunk, false);
                if ($res['error']) return $aiError($res['error']);
                foreach ($res['rows'] as $n => $r) $final[$n] = $r;
            }
            if (empty($final)) {
                \Log::warning('MassAddAiPrice unreadable answer');
                return response()->json(['ok' => false, 'error' => "Couldn't read the AI's answer. Try again, or list fewer items."]);
            }

            // 2. Escalate to Sonnet: high value, unclear edition, low confidence,
            //    premium cards, or our own sales for the title are spread out.
            $need = [];
            foreach ($numbered as $n => $l) {
                $r = $final[$n] ?? null;
                // Sonnet only for items priced $30 and up (Sarah 2026-11-16), and only when
                // something about the item needs a second look.
                $p = $r ? (float) preg_replace('/[^0-9.]/', '', (string) ($r['price'] ?? 0)) : 0;
                if ($p < self::ESCALATE_MIN_PRICE) {
                    continue;
                }
                if (!$r
                    || !empty($r['premium']) || !empty($r['high_value']) || !empty($r['edition_unclear'])
                    || (($r['confidence'] ?? 'high') === 'low')
                    || !empty($conflict[$n])) {
                    $need[$n] = $l;
                }
            }
            foreach (array_chunk($need, self::BATCH_SIZE, true) as $chunk) {
                $res = $ask(self::MODEL_ESCALATE, $chunk, true);
                if ($res['error']) {
                    \Log::warning('MassAddAiPrice escalation failed; keeping routine prices', ['status' => $res['error']['status']]);
                    continue;
                }
                foreach ($res['rows'] as $n => $r) $final[$n] = $r;
            }

            // Keep every line in order; a line with no answer comes back with its text only.
            $rows = [];
            foreach ($numbered as $n => $l) {
                $rows[] = $final[$n] ?? ['name' => $l];
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
                $floor = $h ? $h['floor'] : 0;
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
                    // 45s are left out (it made them worse), and so is anything graded G+ or lower.
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

    /** Tool the model fills in: one entry per numbered item. */
    private static function priceTool()
    {
        return [
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
                                'n' => ['type' => 'integer', 'description' => 'The item number from the list.'],
                                'name' => ['type' => 'string'],
                                'artist' => ['type' => 'string'],
                                'category' => ['type' => 'string'],
                                'subcategory' => ['type' => 'string'],
                                'price' => ['type' => 'number'],
                                'condition' => ['type' => 'string'],
                                'confidence' => ['type' => 'string', 'enum' => ['high', 'low']],
                                'high_value' => ['type' => 'boolean'],
                                'edition_unclear' => ['type' => 'boolean'],
                                'premium' => [
                                    'type' => 'boolean',
                                    'description' => 'Trading cards only: true if the card is graded / slabbed, a true rookie card, autographed, a scarce insert or parallel, or a sealed box. false for ordinary raw cards and for everything that is not a card.',
                                ],
                            ],
                            'required' => ['n', 'name', 'artist', 'category', 'subcategory', 'price'],
                        ],
                    ],
                ],
                'required' => ['items'],
            ],
        ];
    }

    /** Dollars for one call. Rates are per million tokens; cache writes cost 1.25x input, reads 0.1x. */
    private function costUsd($model, $in, $out, $cacheWrite, $cacheRead)
    {
        $r = self::RATES[$model] ?? self::RATES[self::MODEL_ESCALATE];
        return ($in * $r['in'] + $out * $r['out'] + $cacheWrite * $r['in'] * 1.25 + $cacheRead * $r['in'] * 0.1) / 1000000;
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
            $p90 = $prices[min($n - 1, (int) floor(0.9 * ($n - 1) + 0.5))];

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

            // Sarah 2026-11-16: items that sell fast are not priced cheap. The floor rises
            // with how quickly we historically sold it (median days to first sale).
            if ($medDays !== null && $medDays <= 5) {
                $floor = $p90;
            } elseif ($medDays !== null && $medDays <= 30) {
                $floor = $p75;
            } elseif ($medDays !== null && $medDays <= 60) {
                $floor = ($median + $p75) / 2;
            } else {
                $floor = $median;
            }

            $inStock = (float) DB::table('variation_location_details')
                ->whereIn('product_id', $created->keys()->all())
                ->sum('qty_available');

            $match = $sales->pluck('product_id')->unique()->map(function ($id) use ($names) { return $names[$id] ?? ''; })->filter()->unique()->take(6)->values()->all();

            return [
                'match' => $match,
                'sales' => $n,
                'median' => round($median, 2),
                'p75' => round($p75, 2),
                'p90' => round($p90, 2),
                'floor' => round($floor, 2),
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
