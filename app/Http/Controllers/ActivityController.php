<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ActivityController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q', ''));
        $event = (string) $request->query('event', '');

        $logs = ActivityLog::query()
            ->with('user')
            ->when($search !== '', fn ($query) => $query->where(function ($query) use ($search): void {
                $query->where('description', 'like', '%'.$search.'%')
                    ->orWhere('event', 'like', '%'.$search.'%');
            }))
            ->when($event !== '', fn ($query) => $query->where('event', $event))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        $events = ActivityLog::query()->select('event')->distinct()->orderBy('event')->pluck('event');

        return view('activity.index', [
            'user' => $request->user(),
            'logs' => $logs,
            'events' => $events,
            'search' => $search,
            'selectedEvent' => $event,
        ]);
    }
}
