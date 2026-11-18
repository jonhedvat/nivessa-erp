<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Which stores have a physical divider tab for an artist with a "File under"
 * (Pink Floyd -> a "Pink" tab). Ticked on the Genre Bible page; artists with
 * records in a store but no tab show up under "Tabs to make" (Sarah 2026-11-18).
 */
class CreateArtistTabsTable extends Migration
{
    public function up()
    {
        if (Schema::hasTable('artist_tabs')) {
            return;
        }
        Schema::create('artist_tabs', function (Blueprint $table) {
            $table->increments('id');
            $table->string('artist_key', 191); // ArtistGenreLookup::normalizeArtist()
            $table->string('store', 16);       // pico | hollywood
            $table->unsignedInteger('created_by')->nullable();
            $table->timestamps();
            $table->unique(['artist_key', 'store']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('artist_tabs');
    }
}
