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

        $rows = \Cache::remember('margin_check_v2_' . $business_id, 1800, function () use ($business_id) {
            $svc = app(\App\Services\InventoryCheckService::class);
            $q = DB::table('products as p')
                ->leftJoin('categories as c1', 'p.category_id', '=', 'c1.id')
                ->where('p.business_id', $business_id)
                ->where('p.type', '!=', 'modifier')
                ->where(function ($w) {
                    $w->where('c1.name', 'like', '%Sealed%')->orWhere('c1.name', 'like', '%New%');
                })
                ->select(
                    'p.id', 'p.name as product', 'p.artist', 'p.sku', 'c1.name as category',
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
                ];
            }
            return $out;
        });

        if (request()->query('format') === 'json') {
            return response()->json(['count' => count($rows), 'rows' => $rows]);
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
