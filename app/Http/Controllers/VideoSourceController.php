<?php

namespace App\Http\Controllers;

use App\Models\VideoSource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class VideoSourceController extends Controller
{
    public function index(Request $request): View
    {
        return view('sources.index', ['user' => $request->user()]);
    }

    public function create(Request $request): View
    {
        return view('sources.create', ['user' => $request->user()]);
    }

    public function show(Request $request, VideoSource $videoSource): View
    {
        Gate::authorize('view', $videoSource);

        $videoSource->load(['transcript', 'clips' => fn ($query) => $query->orderBy('start_seconds')]);

        return view('sources.show', [
            'user' => $request->user(),
            'source' => $videoSource,
            'clips' => $videoSource->clips,
        ]);
    }
}
