<?php

namespace App\Services;

use App\Exceptions\RenderingException;
use App\Models\Clip;
use App\Models\SubtitleTemplate;
use App\Models\VideoSource;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

class VideoRenderService
{
    public function __construct(
        private readonly ProcessRunner $processes,
        private readonly SubtitleTemplateService $templates,
    ) {}

    /**
     * @return array{video: string, thumbnail: ?string, subtitle: ?string, ass: ?string, size_bytes: int, width: int, height: int, media_hash: string}
     */
    public function render(Clip $clip, VideoSource $source): array
    {
        $width = (int) config('clipflow.media.render.width');
        $height = (int) config('clipflow.media.render.height');
        $start = (float) $clip->start_seconds;
        $duration = (float) $clip->duration_seconds;
        $foregroundPercent = $this->clampInt(
            $clip->foreground_width,
            (int) config('clipflow.media.render.foreground_width_percent.min'),
            (int) config('clipflow.media.render.foreground_width_percent.max'),
            (int) config('clipflow.media.render.foreground_width_percent.default'),
        );
        $blurSigma = $this->clampInt(
            $clip->blur_sigma,
            (int) config('clipflow.media.render.blur_sigma.min'),
            (int) config('clipflow.media.render.blur_sigma.max'),
            (int) config('clipflow.media.render.blur_sigma.default'),
        );
        $foregroundWidth = $this->evenDimension($width * $foregroundPercent / 100);
        $foregroundHeight = $this->evenDimension($height * $foregroundPercent / 100);

        $videoRelative = sprintf('clips/%d/video.mp4', $clip->id);
        $thumbnailRelative = sprintf('clips/%d/thumbnail.jpg', $clip->id);
        $subtitleRelative = sprintf('clips/%d/subtitles.srt', $clip->id);
        $assRelative = sprintf('clips/%d/subtitles.ass', $clip->id);

        $videoPath = $this->absolutePath($videoRelative);
        $thumbnailPath = $this->absolutePath($thumbnailRelative);
        $subtitlePath = $this->absolutePath($subtitleRelative);
        $assPath = $this->absolutePath($assRelative);

        $this->deleteExisting([$videoPath, $thumbnailPath, $subtitlePath, $assPath]);
        File::ensureDirectoryExists(dirname($videoPath));

        $subtitleRelativeResult = null;
        $assRelativeResult = null;
        $subtitleFilter = '';
        $transcript = $source->transcript;

        if ($clip->subtitles_enabled && $transcript !== null) {
            $entries = $this->subtitleEntries($start, $duration, $transcript->segments ?? []);

            if ($entries !== []) {
                $template = $clip->subtitleTemplate
                    ?? $source->subtitleTemplate
                    ?? $this->templates->active();

                $this->writeSrt($subtitlePath, $entries);
                $this->writeAss($assPath, $entries, $width, $height, $template, $this->subtitleMarginV($width, $height, $foregroundPercent, $source, $template));
                $subtitleRelativeResult = $subtitleRelative;
                $assRelativeResult = $assRelative;
                $subtitleFilter = ',ass=filename='.$this->escapeFilterPath($assPath);
            }
        }

        $videoFilter = sprintf(
            '[0:v]split=2[bg][fg];[bg]scale=%1$d:%2$d:force_original_aspect_ratio=increase,crop=%1$d:%2$d,setsar=1,gblur=sigma=%3$d[background];[fg]scale=%4$d:%5$d:force_original_aspect_ratio=decrease:force_divisible_by=2,setsar=1[foreground];[background][foreground]overlay=(W-w)/2:(H-h)/2:shortest=1,fps=%6$d%7$s,format=yuv420p[rendered]',
            $width,
            $height,
            $blurSigma,
            $foregroundWidth,
            $foregroundHeight,
            (int) config('clipflow.media.render.fps'),
            $subtitleFilter,
        );

        try {
            $this->processes->run([
                config('clipflow.media.ffmpeg_binary'),
                '-hide_banner',
                '-loglevel', 'error',
                '-y',
                '-ss', $this->timestamp($start),
                '-i', $this->sourcePath($source),
                '-t', $this->timestamp($duration),
                '-filter_complex', $videoFilter,
                '-map', '[rendered]',
                '-map', '0:a?',
                '-c:v', 'libx264',
                '-preset', (string) config('clipflow.media.render.preset'),
                '-crf', (string) config('clipflow.media.render.crf'),
                '-r', (string) config('clipflow.media.render.fps'),
                '-pix_fmt', 'yuv420p',
                '-c:a', 'aac',
                '-b:a', (string) config('clipflow.media.render.audio_bitrate'),
                '-movflags', '+faststart',
                $videoPath,
            ], (int) config('clipflow.media.render.timeout'));
        } catch (\Throwable $exception) {
            throw new RenderingException(
                'Render klip gagal. Pastikan FFmpeg tersedia; subtitle juga memerlukan build dengan libass.',
                'render_ffmpeg',
                $exception,
            );
        }

        if (! is_file($videoPath) || filesize($videoPath) === 0) {
            throw new RenderingException('File render tidak berhasil dibuat.', 'render_output');
        }

        $thumbnailAt = min($start + 1.0, $start + max(0.2, $duration - 0.2));
        $thumbnailRelativeResult = $thumbnailRelative;

        try {
            $this->processes->run([
                config('clipflow.media.ffmpeg_binary'),
                '-hide_banner',
                '-loglevel', 'error',
                '-y',
                '-ss', $this->timestamp($thumbnailAt),
                '-i', $this->sourcePath($source),
                '-frames:v', '1',
                '-vf', 'scale=720:-2',
                '-q:v', '3',
                $thumbnailPath,
            ], 120);
        } catch (\Throwable) {
            $thumbnailRelativeResult = null;
        }

        return [
            'video' => $videoRelative,
            'thumbnail' => $thumbnailRelativeResult,
            'subtitle' => $subtitleRelativeResult,
            'ass' => $assRelativeResult,
            'size_bytes' => (int) filesize($videoPath),
            'width' => $width,
            'height' => $height,
            'media_hash' => hash_file('sha256', $videoPath),
        ];
    }

    /**
     * @param  array<int, array{start: float, end: float, text: string}>  $segments
     * @return array<int, array{start: float, end: float, text: string}>
     */
    private function subtitleEntries(float $clipStart, float $clipDuration, array $segments): array
    {
        $clipEnd = $clipStart + $clipDuration;
        $entries = [];

        foreach ($segments as $segment) {
            $start = (float) $segment['start'];
            $end = (float) $segment['end'];

            if ($end <= $clipStart || $start >= $clipEnd) {
                continue;
            }

            $relativeStart = max(0.0, $start - $clipStart);
            $relativeEnd = min($clipDuration, $end - $clipStart);

            if ($relativeEnd - $relativeStart < 0.05) {
                continue;
            }

            $entries[] = [
                'start' => $relativeStart,
                'end' => $relativeEnd,
                'text' => str_replace(["\r", "\n"], ' ', trim((string) $segment['text'])),
            ];
        }

        if ($entries === []) {
            return [];
        }

        return $entries;
    }

    /**
     * @param  array<int, array{start: float, end: float, text: string}>  $entries
     */
    private function writeSrt(string $path, array $entries): void
    {
        $lines = [];

        foreach ($entries as $index => $entry) {
            $lines[] = implode("\n", [
                (string) ($index + 1),
                $this->srtTimestamp($entry['start']).' --> '.$this->srtTimestamp($entry['end']),
                $entry['text'],
                '',
            ]);
        }

        file_put_contents($path, implode("\n", $lines));
    }

    /**
     * @param  array<int, array{start: float, end: float, text: string}>  $entries
     */
    private function writeAss(string $path, array $entries, int $width, int $height, SubtitleTemplate $template, int $marginV): void
    {
        $font = str_replace([',', "'"], '', (string) $template->font) ?: 'Arial';
        $fontSize = $this->clampInt($template->font_size, 10, 72, 30);
        $outlineWidth = $this->clampInt($template->outline_width, 0, 10, 2);
        $maxChars = $this->clampInt($template->max_chars_per_line, 10, 120, 42);
        $maxLines = $this->clampInt($template->max_lines, 1, 6, 2);
        $bold = $template->bold ? -1 : 0;
        $marginX = $this->clampInt($template->margin_x, 0, 300, 30);

        $useBox = is_string($template->background_color)
            && trim($template->background_color) !== ''
            && (int) $template->background_opacity > 0;

        if ($useBox) {
            $borderStyle = 3;
            $outlineColour = $this->assColor($template->background_color, (int) $template->background_opacity);
            $outlineWidth = max(6, $outlineWidth);
            $shadow = 0;
            $backColour = $this->assColor('#000000', 100);
        } else {
            $borderStyle = 1;
            $outlineColour = $this->assColor($template->outline_color, 100);
            $shadow = $this->clampInt($template->shadow, 0, 10, 0);
            $backColour = $this->assColor('#000000', 60);
        }

        $header = implode("\n", [
            '[Script Info]',
            'ScriptType: v4.00+',
            'PlayResX: '.$width,
            'PlayResY: '.$height,
            'WrapStyle: 0',
            'ScaledBorderAndShadow: yes',
            '',
            '[V4+ Styles]',
            'Format: Name, Fontname, Fontsize, PrimaryColour, SecondaryColour, OutlineColour, BackColour, Bold, Italic, Underline, StrikeOut, ScaleX, ScaleY, Spacing, Angle, BorderStyle, Outline, Shadow, Alignment, MarginL, MarginR, MarginV, Encoding',
            sprintf(
                'Style: Default,%s,%d,%s,&H000000FF,%s,%s,%d,0,0,0,100,100,0,0,%d,%d,%d,%d,%d,%d,%d,1',
                $font,
                $fontSize,
                $this->assColor($template->text_color, 100),
                $outlineColour,
                $backColour,
                $bold,
                $borderStyle,
                $outlineWidth,
                $shadow,
                $template->alignment(),
                $marginX,
                $marginX,
                $marginV,
            ),
            '',
            '[Events]',
            'Format: Layer, Start, End, Style, Name, MarginL, MarginR, MarginV, Effect, Text',
        ]);

        $lines = [];

        foreach ($entries as $entry) {
            $wrapped = $this->wrapText($entry['text'], $maxChars);
            $fontOverride = '';

            if (count($wrapped) > $maxLines) {
                $scaled = max(16, (int) round($fontSize * max(0.75, $maxLines / count($wrapped))));
                $fontOverride = '{\fs'.$scaled.'}';
            }

            $text = implode('\N', array_map(fn (string $line): string => $this->escapeAssText($line), $wrapped));

            $lines[] = sprintf(
                'Dialogue: 0,%s,%s,Default,,0,0,0,,%s%s',
                $this->assTimestamp($entry['start']),
                $this->assTimestamp($entry['end']),
                $fontOverride,
                $text,
            );
        }

        file_put_contents($path, $header."\n".implode("\n", $lines)."\n");
    }

    /**
     * @return array<int, string>
     */
    private function wrapText(string $text, int $maxChars): array
    {
        $words = preg_split('/\s+/u', trim($text)) ?: [];
        $lines = [];
        $current = '';

        foreach ($words as $word) {
            if (mb_strlen($word) > $maxChars) {
                if ($current !== '') {
                    $lines[] = $current;
                    $current = '';
                }

                foreach (mb_str_split($word, $maxChars) as $chunk) {
                    $lines[] = $chunk;
                }

                continue;
            }

            $candidate = $current === '' ? $word : $current.' '.$word;

            if (mb_strlen($candidate) <= $maxChars) {
                $current = $candidate;
            } else {
                $lines[] = $current;
                $current = $word;
            }
        }

        if ($current !== '') {
            $lines[] = $current;
        }

        return $lines === [] ? [$text] : $lines;
    }

    private function assColor(?string $hex, int $opacity = 100): string
    {
        $hex = ltrim((string) $hex, '#');

        if (strlen($hex) === 3) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }

        if (preg_match('/^[0-9A-Fa-f]{6}$/', $hex) !== 1) {
            $hex = 'FFFFFF';
        }

        $red = (int) hexdec(substr($hex, 0, 2));
        $green = (int) hexdec(substr($hex, 2, 2));
        $blue = (int) hexdec(substr($hex, 4, 2));

        $opacity = max(0, min(100, $opacity));
        $alpha = (int) round((100 - $opacity) / 100 * 255);

        return sprintf('&H%02X%02X%02X%02X', $alpha, $blue, $green, $red);
    }

    /**
     * Posisi subtitle dihitung dari tepi bawah area video (bukan dasar frame),
     * supaya teks tidak jatuh di area hitam padding.
     */
    private function subtitleMarginV(int $targetWidth, int $targetHeight, int $foregroundPercent, VideoSource $source, SubtitleTemplate $template): int
    {
        $inset = $this->clampInt($template->margin_y, 0, 400, 40);
        $sourceWidth = (int) ($source->width ?? 0);
        $sourceHeight = (int) ($source->height ?? 0);

        if ($sourceWidth <= 0 || $sourceHeight <= 0) {
            return $template->position === 'center' ? 0 : $inset;
        }

        $boxWidth = $targetWidth * max(1, $foregroundPercent) / 100;
        $boxHeight = $targetHeight * max(1, $foregroundPercent) / 100;
        $scale = min($boxWidth / $sourceWidth, $boxHeight / $sourceHeight);
        $fittedHeight = max(1, (int) round($sourceHeight * $scale));
        $padding = max(0, (int) round(($targetHeight - $fittedHeight) / 2));
        $maxInset = max(0, $fittedHeight - 32);

        return match ($template->position) {
            'top' => $padding + min($inset, $maxInset),
            'center' => 0,
            default => $padding + min($inset, $maxInset),
        };
    }

    private function evenDimension(float $value): int
    {
        return max(2, (int) round($value / 2) * 2);
    }

    private function escapeAssText(string $text): string
    {
        return str_replace(
            ['\\', '{', '}'],
            ['\\\\', '\\{', '\\}'],
            $text,
        );
    }

    private function clampInt(mixed $value, int $min, int $max, int $default): int
    {
        if (! is_numeric($value)) {
            return $default;
        }

        return max($min, min($max, (int) $value));
    }

    private function srtTimestamp(float $seconds): string
    {
        $milliseconds = (int) round($seconds * 1000);
        $hours = intdiv($milliseconds, 3600000);
        $minutes = intdiv($milliseconds % 3600000, 60000);
        $secondsPart = intdiv($milliseconds % 60000, 1000);
        $millisecondsPart = $milliseconds % 1000;

        return sprintf('%02d:%02d:%02d,%03d', $hours, $minutes, $secondsPart, $millisecondsPart);
    }

    private function assTimestamp(float $seconds): string
    {
        $centiseconds = (int) round($seconds * 100);
        $hours = intdiv($centiseconds, 360000);
        $minutes = intdiv($centiseconds % 360000, 6000);
        $secondsPart = intdiv($centiseconds % 6000, 100);
        $centisecondsPart = $centiseconds % 100;

        return sprintf('%d:%02d:%02d.%02d', $hours, $minutes, $secondsPart, $centisecondsPart);
    }

    private function timestamp(float $seconds): string
    {
        return number_format($seconds, 3, '.', '');
    }

    private function sourcePath(VideoSource $source): string
    {
        return Storage::disk($source->disk)->path($source->path);
    }

    private function absolutePath(string $relative): string
    {
        return Storage::disk('local')->path($relative);
    }

    private function escapeFilterPath(string $path): string
    {
        $normalized = str_replace('\\', '/', $path);

        return "'".str_replace(
            [':', "'", ',', '[', ']'],
            ['\\:', "\\'", '\\,', '\\[', '\\]'],
            $normalized,
        )."'";
    }

    /**
     * @param  array<int, string>  $paths
     */
    private function deleteExisting(array $paths): void
    {
        foreach ($paths as $path) {
            if (is_file($path)) {
                @unlink($path);
            }
        }
    }
}
