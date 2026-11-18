<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Where an artist's records physically go in the bins ("Pink Floyd" ->
 * "Pink", not F). Set on the Genre Bible page, printed on labels as
 * "FILE UNDER: PINK" (Sarah 2026-11-18).
 */
class CreateArtistFileUnderTable extends Migration
{
    public function up()
    {
        if (Schema::hasTable('artist_file_under')) {
            return;
        }
        Schema::create('artist_file_under', function (Blueprint $table) {
            $table->increments('id');
            $table->string('artist_key', 191)->unique(); // ArtistGenreLookup::normalizeArtist()
            $table->string('name', 191);
            $table->string('file_under', 64);
            $table->unsignedInteger('updated_by')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('artist_file_under');
    }
}
