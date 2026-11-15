<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Shift task check-ins (2026-11-15): non-admin staff tick "I understand"
 * for each of their tasks after the opening cash count, then "I did it" /
 * "I didn't do it" for each one when they close the register.
 */
class CreateTaskShiftCheckinsTable extends Migration
{
    public function up()
    {
        if (Schema::hasTable('task_shift_checkins')) {
            return;
        }
        Schema::create('task_shift_checkins', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('business_id')->index();
            $table->unsignedInteger('user_id')->index();
            $table->unsignedInteger('weekly_task_id')->index();
            $table->unsignedInteger('cash_register_id')->nullable()->index();
            $table->string('phase', 8);      // start | end
            $table->string('response', 16);  // understood | done | not_done
            $table->string('task_title', 200)->nullable(); // snapshot, tasks get renamed/deleted
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('task_shift_checkins');
    }
}
