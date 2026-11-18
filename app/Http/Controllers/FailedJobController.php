<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class FailedJobController extends Controller
{
    public function index(Request $request): View
    {
        $failedJobs = DB::table('failed_jobs')
            ->orderByDesc('failed_at')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString()
            ->through(function (object $job): object {
                $payload = json_decode((string) $job->payload, true);
                $job->job_name = is_array($payload) ? ($payload['displayName'] ?? 'Unknown') : 'Unknown';

                return $job;
            });

        return view('failed-jobs.index', [
            'failedJobs' => $failedJobs,
        ]);
    }

    public function retry(Request $request, string $uuid): RedirectResponse
    {
        abort_unless(Str::isUuid($uuid), 404);

        $exitCode = Artisan::call('queue:retry', ['id' => [$uuid]]);

        return back()->with(
            $exitCode === 0 ? 'success' : 'error',
            $exitCode === 0 ? 'Job dikirim ulang ke antrean.' : 'Job tidak dapat dikirim ulang. Periksa detail job.',
        );
    }

    public function destroy(Request $request, string $uuid): RedirectResponse
    {
        abort_unless(Str::isUuid($uuid), 404);

        Artisan::call('queue:forget', ['id' => $uuid]);

        return back()->with('success', 'Job gagal dihapus dari daftar.');
    }
}
