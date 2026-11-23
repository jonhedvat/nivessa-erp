<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Website Get a Quote funnel: one row per visit (sid) per step, so we can see
// where people leave the form (view -> item_added -> info_step -> quote_shown).
class CreateSellQuoteEventsTable extends Migration
{
    public function up()
    {
        if (Schema::hasTable('sell_quote_events')) {
            return;
        }
        Schema::create('sell_quote_events', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('sid', 40);
            $table->string('step', 24);
            $table->string('detail', 120)->nullable();
            $table->string('ip', 64)->nullable();
            $table->timestamp('created_at')->nullable();
            $table->unique(['sid', 'step']);
            $table->index(['step', 'created_at']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('sell_quote_events');
    }
}
