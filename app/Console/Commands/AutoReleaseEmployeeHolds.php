<?php

namespace App\Console\Commands;

use App\Services\ProductHolds;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Employee holds past their hold until date go back on sale on their own
 * (Sarah, 2026-11-16: staff holds were sitting in the bin for months).
 * Customer holds are left alone. The copy is put back on the website; /holds
 * lists it so someone moves it from the hold bin to the floor.
 */
class AutoReleaseEmployeeHolds extends Command
{
    const NOTE = 'Auto-released: past due. Put the copy back on the floor.';

    protected $signature = 'holds:auto-release-employee {--dry-run}';
    protected $description = 'Release employee holds that are past their hold until date';

    public function handle()
    {
        if (!ProductHolds::enabled()) return 0;
        $today = Carbon::today('America/Los_Angeles')->toDateString();
        $rows = DB::table('product_holds')->where('status', 'active')->where('held_for_type', 'employee')
            ->where('hold_until', '<', $today)->get(['id', 'product_id', 'held_for_name', 'hold_until']);
        foreach ($rows as $r) $this->line("#{$r->id} {$r->held_for_name} until {$r->hold_until} product {$r->product_id}");
        if ($this->option('dry-run') || $rows->isEmpty()) {
            $this->info(($this->option('dry-run') ? 'Dry run: would release ' : 'Released ') . $rows->count());
            return 0;
        }
        $now = Carbon::now();
        DB::table('product_holds')->whereIn('id', $rows->pluck('id'))->where('status', 'active')->update([
            'status' => 'released', 'released_at' => $now, 'released_by' => null,
            'release_note' => self::NOTE, 'updated_at' => $now,
        ]);
        ProductHolds::pushToWebsite($rows->pluck('product_id')->all());
        \Log::info('holds:auto-release-employee released ' . $rows->count());
        $this->info('Released ' . $rows->count());
        return 0;
    }
}
