<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * What Mass Add "Price it" costs us, by employee, pricing session and item.
 *   php artisan ai:pricing-cost --days=30 [--items] [--business=1]
 */
class AiPricingCost extends Command
{
    protected $signature = 'ai:pricing-cost {--days=30 : Look back this many days} {--items : Also list every item} {--business= : Limit to a business_id}';

    protected $description = 'Cost of Mass Add AI pricing by employee, pricing session and item';

    public function handle()
    {
        $since = now()->subDays((int) $this->option('days'));
        $base = DB::table('ai_pricing_usage as u')
            ->leftJoin('users as us', 'us.id', '=', 'u.user_id')
            ->where('u.created_at', '>=', $since);
        if ($this->option('business')) {
            $base->where('u.business_id', (int) $this->option('business'));
        }
        $who = "TRIM(CONCAT(COALESCE(us.first_name,''),' ',COALESCE(us.last_name,'')))";

        $total = (clone $base)->sum('u.cost_usd');
        $this->info(sprintf('Total last %d days: $%.4f', (int) $this->option('days'), $total));

        $this->line('');
        $this->info('By employee');
        $emp = (clone $base)->groupBy('u.user_id')
            ->select(DB::raw("$who as who"), DB::raw('COUNT(DISTINCT u.session_id) sessions'),
                DB::raw('COUNT(*) item_model_calls'), DB::raw('SUM(u.escalated) escalated'), DB::raw('ROUND(SUM(u.cost_usd),4) cost_usd'))
            ->orderByDesc('cost_usd')->get();
        $this->table(['Employee', 'Sessions', 'Item calls', 'Escalated', 'Cost USD'],
            $emp->map(function ($r) { return [$r->who, $r->sessions, $r->item_model_calls, $r->escalated, $r->cost_usd]; })->all());

        $this->line('');
        $this->info('By pricing session');
        $ses = (clone $base)->groupBy('u.session_id', 'u.user_id')
            ->select(DB::raw("$who as who"), DB::raw('MIN(u.created_at) started'), DB::raw('COUNT(DISTINCT u.item_no) items'),
                DB::raw('SUM(u.escalated) escalated_calls'), DB::raw('ROUND(SUM(u.cost_usd),4) cost_usd'))
            ->orderByDesc('started')->limit(200)->get();
        $this->table(['Employee', 'Started', 'Items', 'Escalated calls', 'Cost USD'],
            $ses->map(function ($r) { return [$r->who, $r->started, $r->items, $r->escalated_calls, $r->cost_usd]; })->all());

        if ($this->option('items')) {
            $this->line('');
            $this->info('By item');
            $it = (clone $base)->select(DB::raw("$who as who"), 'u.created_at', 'u.item_text', 'u.model', 'u.escalated', 'u.cost_usd')
                ->orderByDesc('u.created_at')->limit(1000)->get();
            $this->table(['Employee', 'When', 'Item', 'Model', 'Escalated', 'Cost USD'],
                $it->map(function ($r) { return [$r->who, $r->created_at, $r->item_text, $r->model, $r->escalated ? 'yes' : 'no', $r->cost_usd]; })->all());
        }
        return 0;
    }
}
