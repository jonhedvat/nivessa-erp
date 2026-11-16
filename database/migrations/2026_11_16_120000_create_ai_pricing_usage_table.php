<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per item per model call of Mass Add "Price it" (2026-11-16).
 * A batch's tokens and cost are split evenly across its items, so per-item,
 * per-session and per-employee totals add up to what Anthropic bills.
 */
class CreateAiPricingUsageTable extends Migration
{
    public function up()
    {
        if (Schema::hasTable('ai_pricing_usage')) {
            return;
        }
        Schema::create('ai_pricing_usage', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('business_id')->index();
            $table->unsignedInteger('user_id')->index();
            $table->char('session_id', 36)->index();   // one "Price it" click
            $table->unsignedInteger('item_no');
            $table->string('item_text', 255);
            $table->string('model', 40);
            $table->boolean('escalated')->default(false);
            $table->unsignedSmallInteger('batch_size');
            $table->unsignedInteger('input_tokens')->default(0);
            $table->unsignedInteger('output_tokens')->default(0);
            $table->unsignedInteger('cache_write_tokens')->default(0);
            $table->unsignedInteger('cache_read_tokens')->default(0);
            $table->decimal('cost_usd', 12, 6)->default(0);
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('ai_pricing_usage');
    }
}
