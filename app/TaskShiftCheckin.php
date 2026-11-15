<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class TaskShiftCheckin extends Model
{
    protected $table = 'task_shift_checkins';
    protected $guarded = ['id'];

    public function user()
    {
        return $this->belongsTo(\App\User::class, 'user_id');
    }

    public function task()
    {
        return $this->belongsTo(\App\WeeklyTask::class, 'weekly_task_id');
    }
}
