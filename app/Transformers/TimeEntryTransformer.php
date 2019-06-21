<?php

namespace App\Transformers;

use App\TimeEntry;
use Carbon\Carbon;
use League\Fractal\TransformerAbstract;

class TimeEntryTransformer extends TransformerAbstract
{
    public function transform(TimeEntry $entry)
    {
        return [
            'id' => $entry->id,
            'user' => fractal($entry->user, new UserTransformer()),
            'duration' => Carbon::parse($entry->start_date_time)->diffForHumans($entry->end_date_time),
            'start_date_time' => $entry->start_date_time,
            'end_date_time' => $entry->end_date_time
        ];
    }
}
