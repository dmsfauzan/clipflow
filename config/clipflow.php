<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Zona waktu operasional
    |--------------------------------------------------------------------------
    |
    | Semua perhitungan slot upload dan laporan harian memakai zona waktu ini.
    |
    */

    'timezone' => env('CLIPFLOW_TIMEZONE', 'Asia/Jakarta'),

    'media' => [
        'allowed_extensions' => ['mp4', 'mov', 'webm'],
        'allowed_mimes' => ['video/mp4', 'video/quicktime', 'video/webm'],
        'max_upload_bytes' => (int) env('CLIPFLOW_MAX_UPLOAD_BYTES', 2 * 1024 * 1024 * 1024),
        'max_duration_seconds' => (int) env('CLIPFLOW_MAX_DURATION_SECONDS', 7200),
        'ffmpeg_binary' => env('CLIPFLOW_FFMPEG_BINARY', 'ffmpeg'),
        'ffprobe_binary' => env('CLIPFLOW_FFPROBE_BINARY', 'ffprobe'),
        'probe_timeout' => (int) env('CLIPFLOW_PROBE_TIMEOUT', 120),
        'extract_timeout' => (int) env('CLIPFLOW_EXTRACT_TIMEOUT', 1800),
        'render' => [
            'width' => (int) env('CLIPFLOW_RENDER_WIDTH', 720),
            'height' => (int) env('CLIPFLOW_RENDER_HEIGHT', 1280),
            'preset' => env('CLIPFLOW_RENDER_PRESET', 'veryfast'),
            'crf' => (int) env('CLIPFLOW_RENDER_CRF', 23),
            'audio_bitrate' => env('CLIPFLOW_RENDER_AUDIO_BITRATE', '128k'),
            'timeout' => (int) env('CLIPFLOW_RENDER_TIMEOUT', 3600),
            'subtitle' => [
                'font' => env('CLIPFLOW_SUBTITLE_FONT', 'Arial'),
                'font_size' => (int) env('CLIPFLOW_SUBTITLE_FONT_SIZE', 30),
                'margin_v' => (int) env('CLIPFLOW_SUBTITLE_MARGIN_V', 40),
                'outline' => (int) env('CLIPFLOW_SUBTITLE_OUTLINE', 2),
                'shadow' => (int) env('CLIPFLOW_SUBTITLE_SHADOW', 0),
                'bold' => (bool) env('CLIPFLOW_SUBTITLE_BOLD', false),
            ],
        ],
    ],

    'transcription' => ['python_binary' => env('CLIPFLOW_PYTHON_BINARY', 'python'),
        'script' => base_path('scripts/transcribe.py'),
        'language' => env('CLIPFLOW_TRANSCRIPTION_LANGUAGE', 'id'),
        'model' => env('CLIPFLOW_WHISPER_MODEL', 'small'),
        'device' => env('CLIPFLOW_WHISPER_DEVICE', 'auto'),
        'compute_type' => env('CLIPFLOW_WHISPER_COMPUTE_TYPE', 'int8'),
        'cpu_threads' => (int) env('CLIPFLOW_WHISPER_CPU_THREADS', 4),
        'timeout' => (int) env('CLIPFLOW_TRANSCRIPTION_TIMEOUT', 7200),
    ],

    'download' => [
        'ytdlp_binary' => env('CLIPFLOW_YTDLP_BINARY', 'yt-dlp'),
        'format' => env('CLIPFLOW_DOWNLOAD_FORMAT'),
        'max_height' => (int) env('CLIPFLOW_DOWNLOAD_MAX_HEIGHT', 1080),
        'timeout' => (int) env('CLIPFLOW_DOWNLOAD_TIMEOUT', 3600),
        'allowed_hosts' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('CLIPFLOW_DOWNLOAD_ALLOWED_HOSTS', '')),
        ))),
        'cookies_file' => env('CLIPFLOW_YTDLP_COOKIES'),
        'max_concurrent_per_user' => (int) env('CLIPFLOW_IMPORT_MAX_CONCURRENT', 2),
        'dns_check' => (bool) env('CLIPFLOW_DOWNLOAD_DNS_CHECK', true),
        'socket_timeout' => (int) env('CLIPFLOW_YTDLP_SOCKET_TIMEOUT', 30),
        'retries' => (int) env('CLIPFLOW_YTDLP_RETRIES', 3),
    ],

    'ai' => [
        'driver' => env('CLIPFLOW_AI_DRIVER', 'ollama'),
        'base_url' => env('CLIPFLOW_AI_BASE_URL', env('OLLAMA_BASE_URL', 'http://127.0.0.1:11434')),
        'api_key' => env('CLIPFLOW_AI_API_KEY'),
        'model' => env('CLIPFLOW_AI_MODEL', env('OLLAMA_MODEL', 'llama3.1:8b')),
        'response_format' => (bool) env('CLIPFLOW_AI_RESPONSE_FORMAT', true),
        'timeout' => (int) env('CLIPFLOW_AI_TIMEOUT', env('OLLAMA_TIMEOUT', 600)),
        'chunk_seconds' => (int) env('CLIPFLOW_AI_CHUNK_SECONDS', 300),
        'chunk_chars' => (int) env('CLIPFLOW_AI_CHUNK_CHARS', 6000),
        'candidates_per_chunk' => (int) env('CLIPFLOW_AI_CANDIDATES_PER_CHUNK', 5),
        'max_candidates' => (int) env('CLIPFLOW_AI_MAX_CANDIDATES', 30),
        'min_clip_seconds' => (float) env('CLIPFLOW_MIN_CLIP_SECONDS', 15),
        'max_clip_seconds' => (float) env('CLIPFLOW_MAX_CLIP_SECONDS', 60),
        'timestamp_snap_tolerance' => (float) env('CLIPFLOW_TIMESTAMP_SNAP_TOLERANCE', 2.5),
    ],

    'publishing' => [
        'daily_limit' => (int) env('CLIPFLOW_DAILY_LIMIT', 30),
        'slot_start' => env('CLIPFLOW_SLOT_START', '08:00'),
        'slot_end' => env('CLIPFLOW_SLOT_END', '22:30'),
        'slot_interval_minutes' => (int) env('CLIPFLOW_SLOT_INTERVAL_MINUTES', 30),
        'max_concurrent' => (int) env('CLIPFLOW_MAX_CONCURRENT_UPLOADS', 1),
        'overdue_grace_minutes' => (int) env('CLIPFLOW_OVERDUE_GRACE_MINUTES', 120),
        'dispatches_per_run' => (int) env('CLIPFLOW_DISPATCHES_PER_RUN', 3),
        'auto_schedule' => (bool) env('CLIPFLOW_AUTO_SCHEDULE', false),
        'default_privacy' => env('CLIPFLOW_DEFAULT_PRIVACY', 'private'),
        'safe_retries' => (int) env('CLIPFLOW_SAFE_RETRIES', 2),
        'retry_backoff_minutes' => [5, 20],
        'horizon_days' => (int) env('CLIPFLOW_SCHEDULE_HORIZON_DAYS', 60),
        'youtube_daily_quota_units' => (int) env('YOUTUBE_DAILY_QUOTA_UNITS', 10000),
        'youtube_upload_cost_units' => (int) env('YOUTUBE_UPLOAD_COST_UNITS', 1600),
        'youtube_quota_reserve_units' => (int) env('YOUTUBE_QUOTA_RESERVE_UNITS', 200),
    ],

    'youtube' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect_uri' => env('GOOGLE_REDIRECT_URI', rtrim(env('APP_URL', 'http://localhost:8000'), '/').'/channels/youtube/callback'),
        'scopes' => [
            'https://www.googleapis.com/auth/youtube.upload',
            'https://www.googleapis.com/auth/youtube.readonly',
        ],
        'auth_url' => 'https://accounts.google.com/o/oauth2/v2/auth',
        'token_url' => 'https://oauth2.googleapis.com/token',
        'api_url' => 'https://www.googleapis.com/youtube/v3',
        'upload_url' => 'https://www.googleapis.com/upload/youtube/v3/videos',
        'request_timeout' => (int) env('GOOGLE_REQUEST_TIMEOUT', 60),
        'upload_timeout' => (int) env('GOOGLE_UPLOAD_TIMEOUT', 3600),
    ],

    'retention' => [
        'source_days' => (int) env('CLIPFLOW_SOURCE_RETENTION_DAYS', 0),
        'clip_days' => (int) env('CLIPFLOW_CLIP_RETENTION_DAYS', 0),
    ],

    'notifications' => [
        'telegram' => [
            'enabled' => (bool) env('CLIPFLOW_TELEGRAM_ENABLED', false),
            'bot_token' => env('TELEGRAM_BOT_TOKEN'),
            'chat_id' => env('TELEGRAM_CHAT_ID'),
        ],
    ],

];
