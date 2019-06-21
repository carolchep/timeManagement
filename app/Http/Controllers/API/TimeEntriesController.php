<?php

namespace App\Http\Controllers\API;

use App\TimeEntry;
use App\Transformers\TimeEntryTransformer;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class TimeEntriesController extends Controller
{
    public function index(Request $request)
    {
        return response()->json([
            'data' => fractal($request->user()->timeEntries, new TimeEntryTransformer())
        ], 200);
    }

    public function store(Request $request)
    {
        $this->validate($request, [
            'task_name' => 'required',
            'start_date_time' => 'required',
            'end_date_time' => 'required'
        ]);

        return response()->json([
            'data' => fractal(
                $request->user()->timeEntries()->create($request->all()),
                new TimeEntryTransformer()
            )
        ]);
    }

    public function update(Request $request, TimeEntry $entry)
    {
        $this->validate($request, [
            'task_name' => 'required',
            'start_date_time' => 'required',
            'end_date_time' => 'required'
        ]);

        $entry->task_name = $request->task_name;
        $entry->start_date_time = $request->start_date_time;
        $entry->end_date_time = $request->end_date_time;
        $entry->save();

        return response()->json([
            'data' => fractal($entry, new TimeEntryTransformer())
        ]);
    }

    public function delete(TimeEntry $entry)
    {
        $entry->delete();
        return response()->json([
            'message' => 'entry deleted'
        ]);
    }

    public function show(TimeEntry $entry)
    {
        return response()->json([
            'data' => fractal($entry, new TimeEntryTransformer())
        ]);
    }
}
