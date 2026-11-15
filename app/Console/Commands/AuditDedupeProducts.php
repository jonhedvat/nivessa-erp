<?php

namespace App\Console\Commands;

use Illuminate\Support\Facades\DB;
use Illuminate\Console\Command;

/**
 * Audit nivessa:dedupe-empty-products (Sarah 2026-11-14): a deactivated product
 * is only a true duplicate if the SAME person has an ACTIVE product with the
 * same name, same category and same subcategory created within --window
 * seconds of it. Anything else is reported, and with --commit reactivated
 * (log: storage/app/dedupe-audit-reactivated-*.json).
 *
 *   php artisan nivessa:audit-dedupe            # report only
 *   php artisan nivessa:audit-dedupe --commit   # reactivate the mismatches
 */
class AuditDedupeProducts extends Command
{
    protected $signature = 'nivessa:audit-dedupe
                            {--window=300 : Max seconds between the deactivated copy and its active twin}
                            {--commit : Reactivate products that fail the check}';

    protected $description = 'Check products deactivated as duplicates; reactivate any without a matching active twin.';

    public function handle()
    {
        ini_set('memory_limit', '1024M');
        $window = (int) $this->option('window');
        $ids = [];
        foreach (glob(storage_path('app/dedupe-empty-undo-*.json')) as $f) {
            $list = json_decode(file_get_contents($f), true) ?: [];
            $this->line(basename($f) . ': ' . count($list));
            foreach ($list as $id) $ids[(int) $id] = true;
        }
        $ids = array_keys($ids);
        $this->line('deactivated products to check: ' . count($ids));

        $norm = function ($s) { return mb_strtolower(trim(preg_replace('/\s+/', ' ', (string) $s))); };

        $dead = collect();
        foreach (array_chunk($ids, 2000) as $chunk) {
            $dead = $dead->merge(DB::table('products')->whereIn('id', $chunk)
                ->get(['id', 'name', 'sku', 'created_by', 'created_at', 'category_id', 'sub_category_id', 'is_inactive', 'business_id']));
        }
        $stillInactive = $dead->where('is_inactive', 1);
        $this->line('still inactive: ' . $stillInactive->count());

        // Active products by the same people, same names.
        $users = $stillInactive->pluck('created_by')->unique()->values()->all();
        $active = [];
        $byNameOnly = [];
        foreach (DB::table('products')->where('is_inactive', 0)->whereIn('created_by', $users)
            ->get(['id', 'name', 'created_by', 'created_at', 'category_id', 'sub_category_id', 'business_id']) as $p) {
            $active[$p->business_id . '|' . $p->created_by . '|' . $norm($p->name) . '|' . $p->category_id . '|' . $p->sub_category_id][] = strtotime($p->created_at);
            $byNameOnly[$p->business_id . '|' . $p->created_by . '|' . $norm($p->name)][] = [strtotime($p->created_at), $p->category_id, $p->sub_category_id];
        }

        $ok = 0;
        $bad = [];
        $reasons = ['category differs' => 0, 'subcategory differs' => 0, 'no active same-name copy nearby' => 0];
        $catNames = DB::table('categories')->pluck('name', 'id');
        $samples = [];
        foreach ($stillInactive as $p) {
            $t = strtotime($p->created_at);
            $match = false;
            foreach ($active[$p->business_id . '|' . $p->created_by . '|' . $norm($p->name) . '|' . $p->category_id . '|' . $p->sub_category_id] ?? [] as $at) {
                if (abs($at - $t) <= $window) { $match = true; break; }
            }
            if ($match) { $ok++; continue; }
            $bad[] = (int) $p->id;
            $why = 'no active same-name copy nearby';
            $twin = null;
            foreach ($byNameOnly[$p->business_id . '|' . $p->created_by . '|' . $norm($p->name)] ?? [] as $r) {
                if (abs($r[0] - $t) <= $window) { $twin = $r; break; }
            }
            if ($twin) $why = $twin[1] != $p->category_id ? 'category differs' : 'subcategory differs';
            $reasons[$why]++;
            if (count($samples) < 40) {
                $samples[] = "#{$p->id} " . substr($p->created_at, 0, 16) . ' "' . mb_substr($p->name, 0, 45) . '" '
                    . ($catNames[$p->category_id] ?? '-') . ' / ' . ($catNames[$p->sub_category_id] ?? '-')
                    . ($twin ? '  vs active twin: ' . ($catNames[$twin[1]] ?? '-') . ' / ' . ($catNames[$twin[2]] ?? '-') : '')
                    . "  [$why]";
            }
        }

        $this->line("\nACCURATE (same person + name + category + subcategory, active twin within {$window}s): $ok");
        $this->line('NOT ACCURATE: ' . count($bad));
        foreach ($reasons as $k => $n) $this->line("  $k: $n");
        $this->line("\nsamples:");
        foreach ($samples as $s) $this->line('  ' . $s);

        if (!$this->option('commit')) {
            $this->line("\nREPORT ONLY - re-run with --commit to reactivate the NOT ACCURATE ones.");
            return 0;
        }
        $file = storage_path('app/dedupe-audit-reactivated-' . date('Ymd-His') . '.json');
        file_put_contents($file, json_encode($bad));
        $n = 0;
        foreach (array_chunk($bad, 1000) as $chunk) {
            $n += DB::table('products')->whereIn('id', $chunk)->update(['is_inactive' => 0, 'updated_at' => now()]);
            DB::table('product_stock_cache')->whereIn('product_id', $chunk)->update(['is_inactive' => 0]);
        }
        $this->line("\nReactivated $n products. Log: $file");
        return 0;
    }
}
