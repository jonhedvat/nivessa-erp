<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sarah 2026-11-17: market prices for Pokemon / Magic / Yu-Gi-Oh! cards
 * (weekly `collectibles:import`) and Funko Pops (saved from eBay lookups), so
 * the website quote pays 20% of real value on $10+ items instead of bulk.
 */
class CreateCollectiblePricesTable extends Migration
{
    public function up()
    {
        if (Schema::hasTable('collectible_prices')) {
            return;
        }
        Schema::create('collectible_prices', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('game', 16);            // pokemon | mtg | yugioh | funko
            $table->string('name', 191);
            $table->string('name_norm', 191);
            $table->string('set_name', 191)->nullable();
            $table->string('set_code', 64)->nullable();
            $table->string('number', 32)->nullable();
            $table->string('variant', 64)->nullable(); // holofoil / foil / rarity
            $table->decimal('price', 10, 2);
            $table->string('source', 32);           // tcgplayer | scryfall | ygoprodeck | ebay
            $table->string('url', 500)->nullable();
            $table->unsignedInteger('batch')->default(0);
            $table->timestamps();
            $table->index(['game', 'name_norm']);
            $table->index(['game', 'batch']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('collectible_prices');
    }
}
