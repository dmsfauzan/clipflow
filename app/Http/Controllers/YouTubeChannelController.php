<?php

namespace App\Http\Controllers;

use App\Exceptions\AuthorizationException;
use App\Exceptions\PublishingException;
use App\Models\PublishingChannel;
use App\Services\YouTubeOAuthService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\View\View;

class YouTubeChannelController extends Controller
{
    public function index(Request $request): View
    {
        $channel = PublishingChannel::query()->firstOrCreate(
            ['user_id' => $request->user()->id, 'provider' => 'youtube'],
            ['name' => 'Channel YouTube utama'],
        );

        return view('channels.youtube', [
            'user' => $request->user(),
            'channel' => $channel,
            'oauthConfigured' => app(YouTubeOAuthService::class)->isConfigured(),
        ]);
    }

    public function redirect(Request $request, YouTubeOAuthService $oauth): RedirectResponse
    {
        $this->ensureOwner($request);

        if (! $oauth->isConfigured()) {
            return back()->with('error', 'Kredensial Google OAuth belum diisi di .env.');
        }

        $state = Str::random(64);
        $request->session()->put('youtube_oauth_state', hash('sha256', $state));

        return redirect()->away($oauth->authorizationUrl($state));
    }

    public function callback(Request $request, YouTubeOAuthService $oauth): RedirectResponse
    {
        $expected = (string) $request->session()->pull('youtube_oauth_state');
        $received = hash('sha256', (string) $request->query('state'));

        if ($expected === '' || ! hash_equals($expected, $received)) {
            return redirect()->route('channels.youtube')->with('error', 'Validasi OAuth gagal. Silakan mulai ulang koneksi.');
        }

        if ($request->filled('error')) {
            return redirect()->route('channels.youtube')->with('error', 'Koneksi YouTube dibatalkan atau ditolak.');
        }

        try {
            $token = $oauth->exchangeCode((string) $request->query('code'));
            $identity = $oauth->fetchChannelIdentity($token['access_token']);
            $channel = PublishingChannel::query()->firstOrCreate(
                ['user_id' => $request->user()->id, 'provider' => 'youtube'],
                ['name' => 'Channel YouTube utama'],
            );
            $oauth->connect($channel, $token, $identity, $request->user());

            return redirect()->route('channels.youtube')->with('success', 'Channel YouTube berhasil terhubung.');
        } catch (PublishingException $exception) {
            return redirect()->route('channels.youtube')->with('error', $exception->getMessage());
        }
    }

    public function destroy(Request $request, PublishingChannel $publishingChannel, YouTubeOAuthService $oauth): RedirectResponse
    {
        Gate::authorize('update', $publishingChannel);
        $oauth->disconnect($publishingChannel, $request->user());

        return back()->with('success', 'Channel YouTube diputus. Jadwal aktif tidak diunggah sampai channel disambungkan kembali.');
    }

    private function ensureOwner(Request $request): void
    {
        if (! $request->user()) {
            throw new AuthorizationException('Login diperlukan.');
        }
    }
}
