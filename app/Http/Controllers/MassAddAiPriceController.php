<?php

namespace App\Http\Controllers;

use App\Category;
use GuzzleHttp\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Sarah 2026-11-15: "Price it for me" on Mass Add → Bulk Product Entry.
 * Staff type a rough list ("Rumours LP VG+, Thriller sealed") and Claude
 * returns the CSV lines the bulk parser already understands
 * (Product Name,Artist,Category,Subcategory,SKU,Price,) — the same thing
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

        $system = "You price used and new items for Nivessa, a retail record store with shops on Pico Blvd and in Hollywood, Los Angeles. "
            . "Staff type a rough list of items they are adding to inventory. For each item, work out the product name and artist, "
            . "pick the best category and subcategory, and give a fair in-store retail price based on current market value (Discogs / eBay sold prices) for the format and condition given. "
            . "If no condition is given, assume used in good condition (VG+). If no format is given, assume LP vinyl.\n"
            . "Staff may write a note or instruction instead of an item (for example 'here are a few DVDs I want to price' or 'all of these are sealed'). "
            . "Do not output those lines as items; apply them to the items they describe (format, condition, category).\n\n"
            . "Return ONLY one line per item, in this exact format, with no header, no numbering, no commentary and no code fences:\n"
            . "Product Name,Artist,Category,Subcategory,SKU,Price,\n"
            . "Leave SKU empty. Price is a plain number like 24.99. Do not use commas inside names (drop them or use a space). "
            . "If something is not music (a toy, a book, a card), use the closest category from the list.\n\n"
            . "Copy Category and Subcategory exactly from this list (Category: Subcategories). LPs / albums go in Vinyl - Used or Vinyl - Sealed, not 7\", 45 RPM:\n"
            . $categoryList;

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

            $text = '';
            foreach (($data['content'] ?? []) as $block) {
                if (($block['type'] ?? '') === 'text') {
                    $text .= $block['text'];
                }
            }
            // Strip any code fences / header line the model adds anyway.
            $lines = [];
            foreach (preg_split('/\r?\n/', $text) as $line) {
                $line = trim($line);
                if ($line === '' || strpos($line, '```') === 0) continue;
                if (preg_match('/^"?(product\s*)?name"?\s*,\s*"?artist/i', $line)) continue;
                if (substr_count($line, ',') < 5) continue;
                $lines[] = $line;
            }
            if (empty($lines)) {
                return response()->json(['ok' => false, 'error' => "Couldn't read the AI's answer. Try again, or list fewer items."]);
            }

            // Sarah 2026-11-15: check OUR sales history so the AI never underprices
            // something we already sell for more, or something that flies off the shelf.
            $notes = [];
            foreach ($lines as $i => $line) {
                $cols = str_getcsv($line);
                if (count($cols) < 6) continue;
                $h = $this->storeHistory($businessId, trim($cols[0]), trim($cols[1]), trim($cols[2]));
                if (!$h) continue;
                $ai = (float) preg_replace('/[^0-9.]/', '', $cols[5]);
                $floor = $h['fast'] ? $h['p75'] : $h['median'];
                $note = ['item' => trim($cols[0]) . (trim($cols[1]) !== '' ? ' - ' . trim($cols[1]) : ''),
                    'ai' => $ai, 'price' => $ai, 'sales' => $h['sales'], 'median' => $h['median'],
                    'days' => $h['days'], 'fast' => $h['fast'], 'in_stock' => $h['in_stock']];
                if ($floor > 0 && $floor > $ai) {
                    $cols[5] = number_format($floor, 2, '.', '');
                    $lines[$i] = implode(',', $cols);
                    $note['price'] = (float) $cols[5];
                }
                $notes[] = $note;
            }

            return response()->json(['ok' => true, 'csv' => implode("\n", $lines), 'count' => count($lines), 'history' => $notes]);
        } catch (\Exception $e) {
            \Log::warning('MassAddAiPrice failed', ['error' => $e->getMessage()]);
            return response()->json(['ok' => false, 'error' => "Couldn't reach the AI service. Try again in a minute."]);
        }
    }

    /**
     * What we've actually sold this item for in the last 2 years, and how fast.
     * Match: same product name + same category (CD vs vinyl vs DVD price very
     * differently), artist must match when both sides have one. Needs 2+ sales.
     * "fast" = copies usually sell within 30 days of being added; for fast
     * sellers the floor is the 75th-percentile sale price, otherwise the median.
     */
    private function storeHistory($businessId, $name, $artist, $category)
    {
        if (mb_strlen($name) < 2) return null;
        try {
            $pq = DB::table('products as p')
                ->leftJoin('categories as c', 'c.id', '=', 'p.category_id')
                ->where('p.business_id', $businessId)
                ->where('p.name', $name);
            if ($category !== '') {
                $pq->where('c.name', $category);
            }
            if ($artist !== '') {
                $pq->where(function ($a) use ($artist) {
                    $a->whereNull('p.artist')->orWhere('p.artist', '')->orWhere('p.artist', $artist);
                });
            }
            $products = $pq->limit(200)->get(['p.id', 'p.created_at']);
            if ($products->isEmpty()) return null;
            $created = $products->pluck('created_at', 'id');

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
            if ($sales->count() < 2) return null;

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

            return [
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
