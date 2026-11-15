<?php

namespace App\Http\Controllers;

use App\Category;
use GuzzleHttp\Client;
use Illuminate\Http\Request;

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
            . "If no condition is given, assume used in good condition (VG+). If no format is given, assume LP vinyl.\n\n"
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

            return response()->json(['ok' => true, 'csv' => implode("\n", $lines), 'count' => count($lines)]);
        } catch (\Exception $e) {
            \Log::warning('MassAddAiPrice failed', ['error' => $e->getMessage()]);
            return response()->json(['ok' => false, 'error' => "Couldn't reach the AI service. Try again in a minute."]);
        }
    }
}
