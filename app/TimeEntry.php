<?php

namespace App;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class TimeEntry extends Model
{
    protected $fillable = ['task_name', 'start_date_time','end_date_time'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function setStartDateTimeAttribute($value)
    {
        $this->attributes['start_date_time'] = Carbon::parse($value);
    }

    public function setEndDateTimeAttribute($value)
    {
        $this->attributes['end_date_time'] = Carbon::parse($value);
    }
}
