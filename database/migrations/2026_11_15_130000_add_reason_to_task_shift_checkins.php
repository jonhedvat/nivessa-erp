<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Why a task wasn't done, required with an end-of-shift "I didn't do it". */
class AddReasonToTaskShiftCheckins extends Migration
{
    public function up()
    {
        if (Schema::hasTable('task_shift_checkins') && !Schema::hasColumn('task_shift_checkins', 'reason')) {
            Schema::table('task_shift_checkins', function (Blueprint $table) {
                $table->text('reason')->nullable()->after('response');
            });
        }
    }

    public function down()
    {
        if (Schema::hasColumn('task_shift_checkins', 'reason')) {
            Schema::table('task_shift_checkins', function (Blueprint $table) {
                $table->dropColumn('reason');
            });
        }
    }
}
