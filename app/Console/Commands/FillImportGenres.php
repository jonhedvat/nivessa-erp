<?php

namespace App\Console\Commands;

use App\Services\DistributorInvoiceImport;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Fill the genre (sub_category_id) on distributor-invoice-imported products
 * that came in without one (Alliance/AMS/... importers never set it before
 * 11/23, and discogs:backfill-genres skips them: no Discogs release id).
 * Same lookup the importer now uses. Dry run unless --commit; writes an undo
 * log to storage/app/fill-import-genres-<ts>.json.
 *
 * php artisan products:fill-import-genres [--commit]
 */
class FillImportGenres extends Command
{
    protected $signature = 'products:fill-import-genres {--business=1} {--commit}';
    protected $description = 'Fill missing genres on invoice-imported products (dry run unless --commit).';

    public function handle()
    {
        $bid = (int) $this->option('business');
        $commit = (bool) $this->option('commit');
        $rows = DB::table('products as p')->join('categories as c', 'c.id', '=', 'p.category_id')
            ->where('p.business_id', $bid)->where('p.added_via', 'like', '%\_invoice')
            ->where(function ($q) { $q->whereNull('p.sub_category_id')->orWhere('p.sub_category_id', 0); })
            ->orderBy('p.id')->get(['p.id', 'p.name', 'p.artist', 'p.category_id', 'c.name as cat']);

        $undo = [];
        $filled = 0;
        foreach ($rows as $r) {
            $artist = $r->artist ?: DistributorInvoiceImport::artistFromName($r->name);
            $sub = DistributorInvoiceImport::genreSubCategoryFor($bid, $r->category_id, $artist);
            $subName = $sub ? DB::table('categories')->where('id', $sub)->value('name') : null;
            $this->line(($sub ? 'FILL' : 'SKIP') . " | {$r->id} | {$r->cat} | {$r->name} | " . ($subName ?? '(no match)'));
            if ($sub) {
                $filled++;
                if ($commit) {
                    $n = DB::table('products')->where('id', $r->id)
                        ->where(function ($q) { $q->whereNull('sub_category_id')->orWhere('sub_category_id', 0); })
                        ->update(['sub_category_id' => $sub, 'updated_at' => now()]);
                    if ($n) { $undo[] = ['id' => $r->id, 'sub_category_id' => null]; }
                }
            }
        }
        if ($commit && $undo) {
            $f = 'fill-import-genres-' . date('Ymd-His') . '.json';
            \Storage::put($f, json_encode($undo));
            $this->info("undo log: storage/app/{$f}");
        }
        $this->info(($commit ? 'Filled' : 'Would fill') . " {$filled} of " . count($rows) . ' products missing a genre.');
        return 0;
    }
}
