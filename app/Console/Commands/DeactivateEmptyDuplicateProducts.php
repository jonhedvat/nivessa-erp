<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Deactivate accidental double-adds (Sarah 2026-11-14: "fix over 2 years").
 *
 * A "double" = same name, same business, same person, created within
 * --window seconds of the previous one (mostly a double-clicked Save).
 *
 * Only EMPTY copies are touched: zero stock at every location AND the product
 * (or any of its variations) appears in no table other than its own
 * definition rows (no sales, purchases, adjustments, transfers, BFC lines,
 * listings, ...). Copies that both hold stock are left alone because they may
 * be real separate copies.
 *
 * Per cluster: if any copy is real, every empty copy is deactivated; if all
 * are empty, the earliest is kept and the rest deactivated.
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

    // Rows in these tables are the product's own definition, not usage.
    private $ownTables = [
        'products', 'variations', 'product_variations', 'variation_location_details',
        'product_locations', 'variation_group_prices', 'product_racks',
        'variation_value_templates',
    ];

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
            ->get(['id', 'name', 'sku', 'created_by', 'created_at']);
        $this->line("scanned products: " . $rows->count());

        // Cluster: same person + same normalized name, chained within $window seconds.
        $groups = [];
        foreach ($rows as $r) {
            $nm = mb_strtolower(trim(preg_replace('/\s+/', ' ', (string) $r->name)));
            if (mb_strlen($nm) < 2) continue;
            $groups[$r->created_by . '|' . $nm][] = $r;
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

        $used = $this->usedProductIds($ids);

        $deactivate = [];
        $keptAllEmpty = 0;
        $byMonth = [];
        $byUser = [];
        $samples = [];
        foreach ($clusters as $c) {
            $empty = array_values(array_filter($c, function ($p) use ($used) { return !isset($used[$p->id]); }));
            if (!$empty) continue;
            if (count($empty) === count($c)) { array_shift($empty); $keptAllEmpty++; }
            foreach ($empty as $p) {
                $deactivate[] = $p;
                $m = substr($p->created_at, 0, 7);
                $byMonth[$m] = ($byMonth[$m] ?? 0) + 1;
                $byUser[$p->created_by] = ($byUser[$p->created_by] ?? 0) + 1;
            }
            if (count($samples) < 40) $samples[] = [$c, $empty];
        }

        $this->line("empty copies to deactivate: " . count($deactivate));
        $this->line("clusters where every copy was empty (earliest kept): $keptAllEmpty");
        $this->line("products left alone because they hold stock or history: " . (count($ids) - count($deactivate)));
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
            $this->line('  ' . substr($c[0]->created_at, 0, 10) . ' "' . mb_substr($c[0]->name, 0, 50) . '"  ' . implode(', ', $parts));
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
        }
        $this->line("\nDeactivated $n products. Undo log: $file");
        return 0;
    }

    /** Product ids that hold stock or appear in any table besides their own definition rows. */
    private function usedProductIds(array $ids)
    {
        $used = [];
        foreach (array_chunk($ids, 2000) as $chunk) {
            foreach (DB::table('variation_location_details')->whereIn('product_id', $chunk)
                ->where('qty_available', '<>', 0)->distinct()->pluck('product_id') as $pid) {
                $used[$pid] = true;
            }
        }

        $varToProduct = [];
        foreach (array_chunk($ids, 2000) as $chunk) {
            foreach (DB::table('variations')->whereIn('product_id', $chunk)->get(['id', 'product_id']) as $v) {
                $varToProduct[$v->id] = $v->product_id;
            }
        }

        $db = DB::getDatabaseName();
        $cols = DB::select("SELECT TABLE_NAME t, COLUMN_NAME c FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = ? AND COLUMN_NAME IN ('product_id', 'variation_id')
            AND TABLE_NAME IN (SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_TYPE = 'BASE TABLE')", [$db, $db]);
        foreach ($cols as $col) {
            if (in_array($col->t, $this->ownTables, true)) continue;
            $isVar = $col->c === 'variation_id';
            $keys = $isVar ? array_keys($varToProduct) : $ids;
            $hits = 0;
            foreach (array_chunk($keys, 2000) as $chunk) {
                foreach (DB::table($col->t)->whereIn($col->c, $chunk)->distinct()->pluck($col->c) as $k) {
                    $pid = $isVar ? ($varToProduct[$k] ?? null) : $k;
                    if ($pid && !isset($used[$pid])) { $used[$pid] = true; }
                    $hits++;
                }
            }
            if ($hits) $this->line("  referenced in {$col->t}.{$col->c}: $hits");
        }
        return $used;
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
        }
        $this->line("Reactivated $n of " . count($ids) . " products from $path");
        return 0;
    }
}
