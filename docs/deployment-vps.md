# Deployment VPS (langkah lanjutan)

Panduan ini bukan script provisioning otomatis. Sesuaikan distro, provider, dan resource.

## Resource minimum praktis

- 4 vCPU / 8 GB RAM untuk Whisper small + FFmpeg serial.
- SSD dengan ruang beberapa kali ukuran video sumber untuk file temporary dan hasil render.
- GPU opsional untuk transkripsi; konfigurasi CUDA faster-whisper perlu disesuaikan.
- MySQL 8, PHP 8.3, Node untuk build, FFmpeg full build, Python venv, yt-dlp, Ollama.

## Web app

1. Checkout source di `/var/www/clipflow`.
2. `composer install --no-dev --optimize-autoloader` dan `npm ci && npm run build`.
3. Set ownership storage/cache untuk user PHP-FPM.
4. Set `.env`: `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://...`, database produksi, Google OAuth, FFmpeg/Python/Ollama.
5. `php artisan migrate --force`. `php artisan storage:link` tidak diperlukan untuk media privat.
6. `php artisan config:cache && php artisan route:cache && php artisan view:cache`.
7. Nginx document root harus ke `/var/www/clipflow/public`, bukan root repository. Batasi body size sesuai kebijakan, misalnya `client_max_body_size 2G`.
8. HTTPS wajib untuk OAuth callback.
9. Untuk impor link: `pip install -U yt-dlp`, set `CLIPFLOW_YTDLP_BINARY`, batasi `CLIPFLOW_DOWNLOAD_ALLOWED_HOSTS` bila perlu, dan simpan cookies milik sendiri di file di luar repository (`CLIPFLOW_YTDLP_COOKIES`).
10. Untuk seleksi AI: pilih driver dengan `CLIPFLOW_AI_DRIVER`. Pakai `ollama` bila inference lokal di server, atau `9router`/OpenAI-compatible dengan `CLIPFLOW_AI_BASE_URL` dan `CLIPFLOW_AI_API_KEY` bila memakai gateway. Jalankan `php artisan clipflow:ai-test` setelah `config:clear` untuk memverifikasi.

## Queue workers (Supervisor)

Jalankan dua program terpisah agar render/transkripsi tidak tersendat oleh upload:

```ini
[program:clipflow-pipeline]
command=php /var/www/clipflow/artisan queue:work database --queue=pipeline --sleep=3 --tries=1 --timeout=7200
directory=/var/www/clipflow
autostart=true
autorestart=true
stopwaitsecs=7200
user=www-data

[program:clipflow-uploads]
command=php /var/www/clipflow/artisan queue:work database --queue=uploads --sleep=3 --tries=1 --timeout=3600
directory=/var/www/clipflow
autostart=true
autorestart=true
stopwaitsecs=3600
user=www-data
```

Pastikan `DB_QUEUE_RETRY_AFTER` lebih besar daripada timeout terpanjang. Pantau `failed_jobs`, CPU/RAM, storage, dan log.

Scheduler juga mengirim heartbeat worker setiap menit. Jika ada job menunggu tetapi tidak ada job yang di-reserve dan heartbeat basi, dashboard menandai worker tidak aktif. Setelah deploy kode/config, restart worker agar tidak memakai config lama.

## Setelah deploy

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\restart-clipflow.ps1
```

Worker lama dapat memegang config lama sementara class baru dimuat, sehingga job bisa gagal dengan error seperti key konfigurasi tidak ditemukan. Restart wajib setelah mengubah `.env` atau kode job.

Batasi thread Whisper agar VPS tetap responsif:

```dotenv
CLIPFLOW_WHISPER_CPU_THREADS=4
```

## Notifikasi & kuota

- Telegram: set `CLIPFLOW_TELEGRAM_ENABLED=true`, `TELEGRAM_BOT_TOKEN`, `TELEGRAM_CHAT_ID`. Notifikasi tidak memblokir pipeline jika gagal.
- Kuota YouTube: sesuaikan `YOUTUBE_DAILY_QUOTA_UNITS`, `YOUTUBE_UPLOAD_COST_UNITS`, `YOUTUBE_QUOTA_RESERVE_UNITS` bila akun Anda punya quota extension. Default hanya mampu ±6 upload/hari.
- Job gagal dapat di-retry dari halaman `/failed-jobs`.

## Scheduler

Cron satu kali per menit:

```cron
* * * * * cd /var/www/clipflow && php artisan schedule:run >> /dev/null 2>&1
```

Scheduler command sudah menggunakan `withoutOverlapping` pada tugas yang relevan.

## Operasional

- Backup database dan storage privat.
- Jangan mengaktifkan demo seeder di produksi.
- Putar credential OAuth bila terkompromi.
- Lakukan rekonsiliasi `needs_reconciliation` sebelum retry.
- PC lokal tidak perlu menyala bila VPS worker, scheduler, Ollama, FFmpeg, dan storage berjalan di VPS. Jika tool masih lokal, PC tetap harus menyala.
- Target 30 upload/hari tetap bergantung pada kuota YouTube, pasokan approval, waktu render, dan kapasitas mesin.
