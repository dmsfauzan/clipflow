<?php

namespace App\Http\Controllers;

use App\Models\Clip;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MediaController extends Controller
{
    public function preview(Request $request, Clip $clip): StreamedResponse
    {
        Gate::authorize('view', $clip);
        $path = $this->path($clip->rendered_path);

        return $this->streamFile($request, $path, 'video/mp4', 'inline', 'klip-'.$clip->id.'.mp4');
    }

    public function thumbnail(Request $request, Clip $clip): StreamedResponse
    {
        Gate::authorize('view', $clip);
        $path = $this->path($clip->thumbnail_path);

        return $this->streamFile($request, $path, 'image/jpeg', 'inline', 'thumbnail-'.$clip->id.'.jpg');
    }

    public function download(Request $request, Clip $clip): StreamedResponse
    {
        Gate::authorize('download', $clip);
        $path = $this->path($clip->rendered_path);

        return $this->streamFile($request, $path, 'video/mp4', 'attachment', 'klip-'.$clip->id.'.mp4');
    }

    private function path(?string $relative): string
    {
        if (! is_string($relative) || $relative === '' || ! is_file(Storage::disk('local')->path($relative))) {
            abort(404, 'File tidak tersedia. Data demo tidak menyertakan file media.');
        }

        return Storage::disk('local')->path($relative);
    }

    private function streamFile(Request $request, string $path, string $mimeType, string $disposition, string $filename): StreamedResponse
    {
        $size = (int) filesize($path);
        $start = 0;
        $end = $size - 1;
        $status = 200;

        $range = (string) $request->header('Range', '');

        if (preg_match('/bytes=(\d*)-(\d*)/', $range, $matches) === 1) {
            if ($matches[1] !== '') {
                $start = (int) $matches[1];
            }

            if ($matches[2] !== '') {
                $end = min((int) $matches[2], $size - 1);
            }

            if ($start <= $end) {
                $status = 206;
            } else {
                $start = 0;
                $end = $size - 1;
            }
        }

        $length = $end - $start + 1;

        return response()->stream(function () use ($path, $start, $length): void {
            $handle = fopen($path, 'rb');

            if ($handle === false) {
                return;
            }

            fseek($handle, $start);
            $remaining = $length;

            while ($remaining > 0 && ! feof($handle)) {
                $chunk = fread($handle, (int) min(8192, $remaining));

                if ($chunk === false) {
                    break;
                }

                echo $chunk;
                $remaining -= strlen($chunk);
                flush();
            }

            fclose($handle);
        }, $status, [
            'Content-Type' => $mimeType,
            'Content-Length' => (string) $length,
            'Accept-Ranges' => 'bytes',
            'Content-Disposition' => sprintf('%s; filename="%s"', $disposition, $filename),
            'Cache-Control' => 'private, max-age=0, must-revalidate',
            ...($status === 206 ? ['Content-Range' => sprintf('bytes %d-%d/%d', $start, $end, $size)] : []),
        ]);
    }
}
