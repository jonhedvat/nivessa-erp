<?php

namespace App\Console\Commands;

use App\Services\CollectiblePriceService;
use Illuminate\Console\Command;

/**
 * Weekly card price refresh for the website quote (Sarah 2026-11-17).
 *   php artisan collectibles:import            all three games
 *   php artisan collectibles:import pokemon    one game
 *   php artisan collectibles:import --test="pokemon:charizard base 4/102"
 */
class ImportCollectiblePrices extends Command
{
    protected $signature = 'collectibles:import {game? : pokemon | mtg | yugioh} {--test=* : game:description lookups to print after}';

    protected $description = 'Refresh Pokemon / Magic / Yu-Gi-Oh! card prices (collectible_prices) used by the website buy quote';

    public function handle(CollectiblePriceService $svc)
    {
        $games = $this->argument('game') ? [$this->argument('game')] : ['pokemon', 'mtg', 'yugioh'];
        $failed = false;
        foreach ($games as $game) {
            $start = microtime(true);
            try {
                $n = $svc->import($game, function ($m) { $this->line($m); });
                $this->info(sprintf('%s: %d rows in %ds', $game, $n, microtime(true) - $start));
            } catch (\Throwable $e) {
                $failed = true;
                $this->error($game . ': ' . $e->getMessage());
            }
        }
        foreach (\DB::table('collectible_prices')->selectRaw('game, count(*) n, sum(price >= 10) over10')->groupBy('game')->get() as $r) {
            $this->line("rows: {$r->game} {$r->n} ({$r->over10} at \$10+)");
        }
        foreach ((array) $this->option('test') as $t) {
            list($g, $d) = array_pad(explode(':', $t, 2), 2, '');
            $r = $svc->lookup($g, $d, false);
            $this->line($t . ' => ' . json_encode($r));
        }
        return $failed ? 1 : 0;
    }
}
