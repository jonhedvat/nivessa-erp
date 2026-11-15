<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Deactivate accidental double-adds (Sarah 2026-11-14: "fix over 2 years").
 *
 * A "double" = same name + artist, same business, same person, created within
 * --window seconds of the previous one (mostly a double-clicked Save).
 *
 * Per cluster the EARLIEST copy (the original) is always kept. Later copies
 * are deactivated, including sold-out ones (their sales history stays).
 * A later copy that still has stock on hand, or stock on an open purchase
 * order, is skipped so no sellable inventory gets hidden. So is a later copy
 * whose real barcode differs from the original's (a different edition).
 *
 * Safe by design:
 *   - Dry-run default; --commit to write.
 *   - Sets is_inactive=1 (nothing deleted). The website treats is_inactive=1 as
 *     unpublished, so empty shells also drop off nivessa.com.
 *   - --commit writes storage/app/dedupe-empty-undo-*.json; --undo=<file>
 *     reactivates every product it touched.
 *
 * Usage:
 *   php artisan nivessa:dedupe-empty-products --days=730
 *   php artisan nivessa:dedupe-empty-products --days=730 --commit
 *   php artisan nivessa:dedupe-empty-products --undo=storage/app/dedupe-empty-undo-20261114-120000.json
 */
class DeactivateEmptyDuplicateProducts extends Command
{
    protected $signature = 'nivessa:dedupe-empty-products
                            {--business=1 : business_id to scope to}
                            {--days=730 : Look-back window (by created_at)}
                            {--window=120 : Max seconds between copies}
                            {--min-age=3 : Skip products created in the last N days}
                            {--undo= : Undo log file to restore from}
                            {--commit : Actually write}';

    protected $description = 'Deactivate empty accidental duplicate products (dry-run default, undo log on commit).';


    public function handle()
    {
        ini_set('memory_limit', '1024M');
        if ($this->option('undo')) {
            return $this->undo($this->option('undo'));
        }

        $biz = (int) $this->option('business');
        $days = (int) $this->option('days');
        $window = (int) $this->option('window');
        $minAge = (int) $this->option('min-age');

        $rows = DB::table('products')
            ->where('business_id', $biz)
            ->where('is_inactive', 0)
            ->where('created_at', '>=', now()->subDays($days))
            ->where('created_at', '<=', now()->subDays($minAge))
            ->orderBy('created_at')->orderBy('id')
            ->get(['id', 'name', 'artist', 'sku', 'created_by', 'created_at']);
        $this->line("scanned products: " . $rows->count());

        // Cluster: same person + same normalized name, chained within $window seconds.
        $groups = [];
        foreach ($rows as $r) {
            $nm = mb_strtolower(trim(preg_replace('/\s+/', ' ', (string) $r->name)));
            if (mb_strlen($nm) < 2) continue;
            $ar = mb_strtolower(trim(preg_replace('/\s+/', ' ', (string) $r->artist)));
            $groups[$r->created_by . '|' . $nm . '|' . $ar][] = $r;
        }
        $clusters = [];
        foreach ($groups as $list) {
            $cur = [$list[0]];
            for ($i = 1; $i < count($list); $i++) {
                $gap = strtotime($list[$i]->created_at) - strtotime($list[$i - 1]->created_at);
                if ($gap <= $window) { $cur[] = $list[$i]; continue; }
                if (count($cur) > 1) $clusters[] = $cur;
                $cur = [$list[$i]];
            }
            if (count($cur) > 1) $clusters[] = $cur;
        }
        $ids = [];
        foreach ($clusters as $c) foreach ($c as $p) $ids[] = (int) $p->id;
        $this->line("duplicate clusters: " . count($clusters) . " (products in them: " . count($ids) . ")");
        if (!$ids) return 0;

        $stocked = $this->stockedProductIds($ids);
        $sold = [];
        foreach (array_chunk($ids, 2000) as $chunk) {
            foreach (DB::table('transaction_sell_lines')->whereIn('product_id', $chunk)->distinct()->pluck('product_id') as $pid) {
                $sold[$pid] = true;
            }
        }

        // Keep the earliest (original) copy; deactivate later copies unless
        // they still hold stock (or stock on an open purchase order).
        $deactivate = [];
        $skippedStocked = 0;
        $skippedBarcode = 0;
        $deactSold = 0;
        $byMonth = [];
        $byUser = [];
        $samples = [];
        foreach ($clusters as $c) {
            $later = array_slice($c, 1);
            $origSku = $this->normSku($c[0]->sku);
            $drop = [];
            foreach ($later as $p) {
                if (isset($stocked[$p->id])) { $skippedStocked++; continue; }
                // A different real barcode = a different edition, not a double-click.
                $sku = $this->normSku($p->sku);
                if ($sku !== $origSku && $sku !== (string) $p->id) { $skippedBarcode++; continue; }
                $drop[] = $p;
                if (isset($sold[$p->id])) $deactSold++;
                $deactivate[] = $p;
                $m = substr($p->created_at, 0, 7);
                $byMonth[$m] = ($byMonth[$m] ?? 0) + 1;
                $byUser[$p->created_by] = ($byUser[$p->created_by] ?? 0) + 1;
            }
            if ($drop && count($samples) < 40) $samples[] = [$c, $drop];
        }

        $this->line("originals kept (earliest of each cluster): " . count($clusters));
        $this->line("later copies to deactivate: " . count($deactivate) . " (of which sold out with sales history: $deactSold)");
        $this->line("later copies SKIPPED because they still have stock / open PO: $skippedStocked");
        $this->line("later copies SKIPPED because they have a different real barcode: $skippedBarcode");
        ksort($byMonth);
        $this->line("\nby month created:");
        foreach ($byMonth as $m => $n) $this->line("  $m  $n");
        arsort($byUser);
        $names = DB::table('users')->whereIn('id', array_keys($byUser))
            ->get(['id', 'first_name', 'last_name'])->keyBy('id');
        $this->line("\nby person:");
        foreach ($byUser as $u => $n) {
            $nm = isset($names[$u]) ? trim($names[$u]->first_name . ' ' . $names[$u]->last_name) : "user $u";
            $this->line("  $nm  $n");
        }
        $this->line("\nsamples (K = kept, X = deactivate):");
        $drop = array_flip(array_map(function ($p) { return $p->id; }, $deactivate));
        foreach ($samples as [$c, $e]) {
            $parts = array_map(function ($p) use ($drop) {
                return (isset($drop[$p->id]) ? 'X' : 'K') . "#{$p->id} {$p->sku} " . substr($p->created_at, 11);
            }, $c);
            $this->line('  ' . substr($c[0]->created_at, 0, 10) . ' "' . mb_substr($c[0]->artist . ' - ' . $c[0]->name, 0, 60) . '"  ' . implode(', ', $parts));
        }

        if (!$this->option('commit')) {
            $this->line("\nDRY RUN - nothing written. Re-run with --commit.");
            return 0;
        }

        $dropIds = array_map(function ($p) { return (int) $p->id; }, $deactivate);
        $file = storage_path('app/dedupe-empty-undo-' . date('Ymd-His') . '.json');
        file_put_contents($file, json_encode($dropIds));
        $n = 0;
        foreach (array_chunk($dropIds, 1000) as $chunk) {
            $n += DB::table('products')->whereIn('id', $chunk)->where('is_inactive', 0)
                ->update(['is_inactive' => 1, 'updated_at' => now()]);
            DB::table('product_stock_cache')->whereIn('product_id', $chunk)->update(['is_inactive' => 1]);
        }
        $this->line("\nDeactivated $n products. Undo log: $file");
        return 0;
    }

    /** Product ids with stock on hand anywhere, or on an open (not yet received) purchase. */
    private function stockedProductIds(array $ids)
    {
        $out = [];
        foreach (array_chunk($ids, 2000) as $chunk) {
            foreach (DB::table('variation_location_details')->whereIn('product_id', $chunk)
                ->where('qty_available', '>', 0)->distinct()->pluck('product_id') as $pid) {
                $out[$pid] = true;
            }
            foreach (DB::table('purchase_lines as pl')->join('transactions as t', 't.id', '=', 'pl.transaction_id')
                ->whereIn('pl.product_id', $chunk)
                ->where(function ($q) {
                    $q->where('t.type', 'purchase_order')
                      ->orWhere(function ($q2) { $q2->where('t.type', 'purchase')->where('t.status', '<>', 'received'); });
                })->distinct()->pluck('pl.product_id') as $pid) {
                $out[$pid] = true;
            }
        }
        return $out;
    }

    private function normSku($sku)
    {
        return strtolower(preg_replace('/[\s\-]+/', '', (string) $sku));
    }

    private function undo($file)
    {
        $path = file_exists($file) ? $file : base_path($file);
        if (!file_exists($path)) { $this->error("No such file: $file"); return 1; }
        $ids = json_decode(file_get_contents($path), true) ?: [];
        $n = 0;
        foreach (array_chunk($ids, 1000) as $chunk) {
            $n += DB::table('products')->whereIn('id', $chunk)
                ->update(['is_inactive' => 0, 'updated_at' => now()]);
            DB::table('product_stock_cache')->whereIn('product_id', $chunk)->update(['is_inactive' => 0]);
        }
        $this->line("Reactivated $n of " . count($ids) . " products from $path");
        return 0;
    }
}
