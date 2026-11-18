<?php

namespace App\Http\Controllers;

use App\Models\Clip;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class ClipReviewController extends Controller
{
    public function index(Request $request): View
    {
        return view('review.index', ['user' => $request->user()]);
    }

    public function show(Request $request, Clip $clip): View
    {
        Gate::authorize('view', $clip);

        $clip->load(['videoSource', 'approvals.user', 'approvals.publishingChannel', 'publicationJob.publishingChannel']);

        $approvalCount = $clip->approvals()->count();

        return view('review.show', [
            'user' => $request->user(),
            'clip' => $clip,
            'approvalCount' => $approvalCount,
        ]);
    }
}
