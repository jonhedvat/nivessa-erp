<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;

/**
 * Margin check (Sarah 2026-11-17): every NEW record/CD in stock, its shelf
 * price vs the AMS (or cheapest distributor) wholesale cost, so we can find
 * what we sell under cost or under target markup. Read only: nothing here
 * changes a price. Admin only.
 */
class MarginCheckController extends Controller
{
    public function index()
    {
        $u = auth()->user();
        $ok = false;
        try {
            $ok = $u && ($u->can('superadmin') || $u->hasRole('Admin#' . $u->business_id) || $u->can('view_purchase_price'));
        } catch (\Throwable $e) {
        }
        abort_unless($ok, 403);
        @ini_set('memory_limit', '1024M');
        @set_time_limit(300);
        $business_id = (int) request()->session()->get('user.business_id');

        $rows = \Cache::remember('margin_check_v4_' . $business_id, 1800, function () use ($business_id) {
            $svc = app(\App\Services\InventoryCheckService::class);
            $q = DB::table('products as p')
                ->leftJoin('categories as c1', 'p.category_id', '=', 'c1.id')
                ->leftJoin('users as au', 'p.created_by', '=', 'au.id')
                ->leftJoin('categories as c2', 'p.sub_category_id', '=', 'c2.id')
                ->where('p.business_id', $business_id)
                ->where('p.type', '!=', 'modifier')
                ->where(function ($w) {
                    $w->where('c1.name', 'like', '%Sealed%')->orWhere('c1.name', 'like', '%New%');
                })
                ->select(
                    'p.id', 'p.name as product', 'p.artist', 'p.sku', 'c1.name as category', 'c2.name as genre', 'p.bin_position', 'p.listing_location', 'p.created_at',
                    DB::raw("TRIM(CONCAT(COALESCE(au.first_name,''), ' ', COALESCE(au.last_name,''))) as added_by"),
                    DB::raw('(select max(v.sell_price_inc_tax) from variations v where v.product_id = p.id and v.deleted_at is null) as price'),
                    DB::raw('(select coalesce(sum(vld.qty_available),0) from variations v join variation_location_details vld on vld.variation_id = v.id where v.product_id = p.id and v.deleted_at is null) as stock')
                )
                ->having('stock', '>', 0)
                ->orderBy('p.id');
            $out = [];
            foreach ($q->cursor() as $r) {
                $price = (float) $r->price;
                if ($price <= 0) {
                    continue;
                }
                $hits = $svc->allSupplierPrices($business_id, $r->artist, $r->product, $r->category, $r->sku);
                if (!$hits) {
                    continue;
                }
                $ams = null;
                foreach ($hits as $h) {
                    if (($h['supplier_key'] ?? '') === 'ams') { $ams = $h; break; }
                }
                $best = $ams ?: $hits[0];
                // Only trust barcode matches: a title match can be today's reissue
                // priced against an old sealed original we bought for $3.
                $skuDigits = preg_replace('/\D/', '', (string) $r->sku);
                $hitDigits = preg_replace('/\D/', '', (string) ($best['upc'] ?? ''));
                $byBarcode = strlen($skuDigits) >= 11 && $hitDigits !== '' && (ltrim($skuDigits, '0') === ltrim($hitDigits, '0'));
                if (!$byBarcode) {
                    foreach ($hits as $h) {
                        $hd = preg_replace('/\D/', '', (string) ($h['upc'] ?? ''));
                        if (strlen($skuDigits) >= 11 && $hd !== '' && ltrim($skuDigits, '0') === ltrim($hd, '0')) { $best = $h; $byBarcode = true; break; }
                    }
                }
                if (!$byBarcode) {
                    continue;
                }
                $cost = (float) $best['cost'];
                if ($cost <= 0) {
                    continue;
                }
                $out[] = [
                    'id' => (int) $r->id,
                    'artist' => (string) $r->artist,
                    'title' => (string) $r->product,
                    'category' => (string) $r->category,
                    'sku' => (string) $r->sku,
                    'stock' => (float) $r->stock,
                    'price' => round($price, 2),
                    'cost' => round($cost, 2),
                    'supplier' => (string) ($best['supplier_label'] ?? $best['supplier_key']),
                    'markup' => round(($price - $cost) / $cost * 100, 1),
                    'margin' => round(($price - $cost) / $price * 100, 1),
                    'genre' => trim((string) $r->genre) !== '' ? trim((string) $r->genre) : 'No genre set',
                    'bin' => trim((string) ($r->bin_position ?: $r->listing_location)),
                    'added' => $r->created_at ? \Carbon\Carbon::parse($r->created_at)->setTimezone('America/Los_Angeles')->format('m/d/y') : '',
                    'added_by' => (string) $r->added_by,
                ];
            }
            return $out;
        });

        $ids = array_column(array_filter($rows, fn ($r) => $r['margin'] < 20), 'id');
        $where = [];
        if ($ids) {
            foreach (DB::table('variation_location_details as vld')
                ->join('variations as v', 'v.id', '=', 'vld.variation_id')
                ->join('business_locations as bl', 'bl.id', '=', 'vld.location_id')
                ->whereIn('v.product_id', $ids)->whereNull('v.deleted_at')->where('vld.qty_available', '>', 0)
                ->get(['v.product_id', 'bl.name', 'vld.qty_available']) as $l) {
                $n = (string) $l->name;
                $lab = stripos($n, 'discogs') !== false ? 'Discogs listing' : (stripos($n, 'warehouse') !== false ? 'Hollywood Warehouse' : (stripos($n, 'pico') !== false ? 'Pico' : (stripos($n, 'hollywood') !== false ? 'Hollywood' : $n)));
                $where[(int) $l->product_id][$lab] = ($where[(int) $l->product_id][$lab] ?? 0) + (float) $l->qty_available;
            }
        }
        foreach ($rows as &$rr) {
            $rr['store'] = isset($where[$rr['id']]) ? implode(', ', array_keys($where[$rr['id']])) : '';
            // Price that earns a 30% margin on the distributor cost, rounded up to the next dollar.
            $rr['suggested'] = (float) ceil($rr['cost'] / 0.70);
        }
        unset($rr);

        if (request()->query('format') === 'json') {
            return response()->json(['count' => count($rows), 'rows' => $rows]);
        }
        if (request()->query('format') !== 'csv') {
            $e = fn ($s) => htmlspecialchars((string) $s, ENT_QUOTES);
            $flag = array_values(array_filter($rows, fn ($r) => $r['margin'] < 20));
            $under = count(array_filter($flag, fn ($r) => $r['price'] < $r['cost']));
            $stores = [];
            foreach ($flag as $r) { foreach (array_filter(explode(', ', $r['store'] ?: 'Not in stock at a store')) as $st) { $stores[$st] = ($stores[$st] ?? 0) + 1; } }
            ksort($stores);
            $pick = (string) request()->query('store', '');
            if ($pick !== '') {
                $flag = array_values(array_filter($flag, fn ($r) => in_array($pick, explode(', ', $r['store'] ?: 'Not in stock at a store'), true)));
            }
            // Group by genre, then walk the bins in order so it's one pass down the aisle.
            usort($flag, fn ($a, $b) => [$a['genre'], $a['bin'] === '' ? 'zzz' : strtolower($a['bin']), strtolower($a['artist'])] <=> [$b['genre'], $b['bin'] === '' ? 'zzz' : strtolower($b['bin']), strtolower($b['artist'])]);
            $byGenre = [];
            foreach ($flag as $r) { $byGenre[$r['genre']][] = $r; }
            $h = '<!doctype html><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Restickering list</title>'
                . '<style>body{font-family:Inter,Arial,sans-serif;color:#2B211E;background:#F6F4F1;margin:0;padding:20px}h1{margin:0 0 6px}h2{margin:22px 0 6px;font-size:18px}table{border-collapse:collapse;width:100%;background:#fff;font-size:14px}td,th{padding:7px 9px;border-bottom:1px solid #E3DDD7;text-align:left;vertical-align:top}th{background:#3B2E2A;color:#FFEE94}.n{text-align:right;font-variant-numeric:tabular-nums;white-space:nowrap}.bad{color:#B3261E;font-weight:700}.new{font-weight:800}.box{width:18px;height:18px;border:2px solid #3B2E2A;border-radius:4px;display:inline-block}p{max-width:900px}.tabs a{display:inline-block;margin:0 6px 6px 0;padding:6px 12px;border-radius:999px;background:#fff;border:1px solid #D8CFC6;color:#2B211E;text-decoration:none;font-weight:700;font-size:13px}.tabs a.on{background:#3B2E2A;color:#FFEE94}@media print{.tabs,.noprint{display:none}body{background:#fff;padding:0}}</style>'
                . '<h1>Restickering list</h1>'
                . '<p>New records in stock earning under 20% margin on what the distributor charges us (barcode matches only). ' . $under . ' are priced under cost. Grouped by genre and sorted by bin. <b>New price</b> = 30% margin on cost, rounded up to the dollar. Nothing has been changed in the ERP. <span class="noprint"><a href="?format=csv">CSV</a> &middot; <a href="javascript:print()">Print</a></span></p>'
                . '<div class="tabs"><a href="?" class="' . ($pick === '' ? 'on' : '') . '">All (' . count(array_filter($rows, fn ($r) => $r['margin'] < 20)) . ')</a>';
            foreach ($stores as $st => $n) { $h .= '<a href="?store=' . rawurlencode($st) . '" class="' . ($pick === $st ? 'on' : '') . '">' . $e($st) . ' (' . $n . ')</a>'; }
            $h .= '</div>';
            foreach ($byGenre as $g => $list) {
                $h .= '<h2>' . $e($g) . ' (' . count($list) . ')</h2><table><tr><th></th><th>Bin</th><th>Artist</th><th>Title</th><th>Format</th><th>Where</th><th class="n">Qty</th><th class="n">Now</th><th class="n">Cost</th><th class="n">New price</th><th>Added</th><th class="noprint"></th></tr>';
                foreach ($list as $r) {
                    $h .= '<tr><td><span class="box"></span></td><td>' . $e($r['bin'] ?: '-') . '</td><td>' . $e($r['artist']) . '</td><td>' . $e($r['title']) . '</td><td>' . $e(str_replace(' - Sealed', '', $r['category'])) . '</td><td>' . $e($r['store']) . '</td><td class="n">' . (int) $r['stock'] . '</td><td class="n ' . ($r['price'] < $r['cost'] ? 'bad' : '') . '">$' . number_format($r['price'], 2) . '</td><td class="n">$' . number_format($r['cost'], 2) . '</td><td class="n new">$' . number_format($r['suggested'], 2) . '</td><td>' . $e($r['added']) . ' ' . $e($r['added_by']) . '</td><td class="noprint"><a href="/products/' . $r['id'] . '/edit">Edit</a></td></tr>';
                }
                $h .= '</table>';
            }
            return response($h);
        }
        $headers = ['Content-Type' => 'text/csv', 'Content-Disposition' => 'inline; filename="margin-check.csv"'];
        $fh = fopen('php://temp', 'w+');
        fputcsv($fh, array_keys($rows[0] ?? ['id' => 0]));
        foreach ($rows as $r) {
            fputcsv($fh, $r);
        }
        rewind($fh);
        return response(stream_get_contents($fh), 200, $headers);
    }
}
