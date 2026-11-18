<p align="center"><img src="public/images/clipflow-logo.svg" width="280" alt="clipflow."></p>

# Clipflow — AI Clip Studio

Aplikasi Laravel untuk menyiapkan klip video vertikal secara otomatis: transkripsi lokal, seleksi cuplikan oleh AI, subtitle, render 9:16, review manusia, approval, penjadwalan, lalu upload ke YouTube dengan OAuth resmi.

Prinsip utama:

- **Tidak ada upload tanpa approval.** Approval mengikat versi konten (judul, caption, file render), channel, privasi, dan deklarasi konten anak.
- **Status tidak dipalsukan.** Data demo diberi label “Mode Demo” dan tidak dihitung dalam statistik nyata.
- **Upload yang belum pasti tidak diulang buta.** Status `needs_reconciliation` memisahkan kegagalan yang belum jelas dari kegagalan aman diulang.
- **Semua file media privat.** Tidak ada media di `public/`; preview/download melewati otorisasi dan mendukung Range.

Stack terpasang: Laravel 13, Livewire 4, Tailwind CSS 4 + Vite 8, MySQL 8, database queue, Python + faster-whisper, yt-dlp, driver AI (Ollama atau 9router/OpenAI-compatible), FFmpeg/FFprobe, YouTube Data API v3.

> Target 30 upload/hari adalah **batas maksimum konfigurasi**, bukan jaminan. Hasil nyata dibatasi jumlah klip yang disetujui, waktu render, PC/server yang menyala, serta kuota dan ketentuan YouTube.

---

## 1. Status implementasi

| Bagian | Status |
| --- | --- |
| Login pemilik, tanpa registrasi publik | Selesai |
| Dashboard Overview + polling | Selesai |
| Upload privat MP4/MOV/WebM + validasi hak | Selesai |
| Impor dari link (yt-dlp) + proteksi SSRF | Selesai (butuh yt-dlp) |
| FFprobe, ekstraksi audio, faster-whisper | Selesai (butuh tool terpasang) |
| Seleksi AI (Ollama atau 9router/OpenAI-compatible) + validasi timestamp | Selesai |
| Render 9:16, H.264/AAC, subtitle burn-in | Selesai (butuh FFmpeg dengan libass) |
| Review, edit, approve/reject, batch approve | Selesai |
| Jejak approval + invalidasi saat konten berubah | Selesai |
| Scheduler slot 08.00–22.30 WIB, batas 30/hari | Selesai |
| Pause/resume antrean upload | Selesai |
| YouTube OAuth + refresh token + disconnect | Selesai (butuh kredensial Google) |
| Resumable upload + rekonsiliasi tanpa duplikat | Selesai (belum diuji ke akun nyata) |
| Retensi file, activity log, command diagnostik | Selesai |
| TikTok/Instagram | Tidak diklaim ada; tidak ada tombol palsu |

---

## 2. Prasyarat

| Kebutuhan | Versi yang diuji |
| --- | --- |
| PHP | 8.3.32 (ekstensi `pdo_mysql`, `fileinfo`, `mbstring`, `openssl`) |
| Composer | 2.10+ |
| Node.js + npm | 24.x + 11.x |
| MySQL | 8.0.30 |
| FFmpeg/FFprobe | build dengan `libx264`, `aac`, `libass` |
| yt-dlp | versi terbaru (hanya diperlukan untuk impor link) |
| Python | 3.10+ |
| Ollama | opsional; alternatif driver AI lokal, mis. `llama3.1:8b` |
| 9router / gateway OpenAI-compatible | opsional; alternatif driver AI dengan API key |

Windows 11 + Laragon adalah lingkungan default dokumentasi ini.

---

## 3. Instalasi lokal (Windows/Laragon)

### 3.1 Dependency PHP & frontend

```powershell
cd C:\laragon\www\clipFlow
composer install
npm install
```

`.env` sudah dibuat dan berisi `APP_KEY`. Jika memulai dari nol:

```powershell
Copy-Item .env.example .env
php artisan key:generate
```

### 3.2 Database MySQL

```powershell
mysql -u root -e "CREATE DATABASE IF NOT EXISTS clipflow CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
php artisan migrate
```

Konfigurasi default ada di `.env`: `DB_DATABASE=clipflow`, `DB_USERNAME=root`, `DB_PASSWORD=` (kosong). Sesuaikan bila perlu.

### 3.3 Akun pemilik

Tidak ada halaman registrasi publik.

```powershell
php artisan clipflow:create-owner --email=owner@clipflow.test
```

Anda akan diminta password (minimal 8 karakter). Jangan pakai password demo untuk server yang dapat diakses publik.

Opsional, data demo terpisah:

```powershell
php artisan clipflow:demo
```

Data demo tidak menyertakan file media dan selalu diberi label **Mode Demo**.

### 3.4 Build aset

Untuk pengembangan:

```powershell
npm run dev
```

Untuk produksi/uji tanpa proses Vite:

```powershell
npm run build
```

Jika muncul `Unable to locate file in Vite manifest`, jalankan `npm run build`.

### 3.5 FFmpeg

Opsi Windows:

```powershell
winget install Gyan.FFmpeg
```

Atau unduh build dari <https://www.gyan.dev/ffmpeg/builds/>, ekstrak, lalu tambahkan folder `bin` ke `PATH`. Verifikasi:

```powershell
ffmpeg -version
ffprobe -version
php artisan clipflow:check
```

Render subtitle membutuhkan FFmpeg build dengan `libass` (umumnya build “full” memilikinya).

### 3.6 Python + faster-whisper

```powershell
python -m venv .venv
.\.venv\Scripts\Activate.ps1
python -m pip install --upgrade pip
pip install -r requirements.txt
```

Lalu set path interpreter di `.env` agar worker memakai venv:

```dotenv
CLIPFLOW_PYTHON_BINARY=C:\laragon\www\clipFlow\.venv\Scripts\python.exe
CLIPFLOW_WHISPER_MODEL=small
CLIPFLOW_TRANSCRIPTION_LANGUAGE=id
CLIPFLOW_WHISPER_DEVICE=auto
CLIPFLOW_WHISPER_COMPUTE_TYPE=int8
CLIPFLOW_WHISPER_CPU_THREADS=4
```

`CLIPFLOW_WHISPER_CPU_THREADS` membatasi thread CPU Whisper agar browsing/Apache tetap responsif saat transkripsi video panjang.

Model `small` cukup seimbang untuk CPU. `medium`/`large-v3` butuh RAM/VRAM jauh lebih besar.

### 3.7 yt-dlp (untuk impor dari link)

Hanya diperlukan jika Anda memakai tab **Impor dari link**.

```powershell
pip install -U yt-dlp
yt-dlp --version
```

Bila Anda memakai venv Python di atas, `yt-dlp` ikut terpasang dari `requirements.txt`. Set path binary di `.env` bila tidak berada di `PATH`:

```dotenv
CLIPFLOW_YTDLP_BINARY=C:\laragon\www\clipFlow\.venv\Scripts\yt-dlp.exe
CLIPFLOW_DOWNLOAD_MAX_HEIGHT=1080
CLIPFLOW_DOWNLOAD_TIMEOUT=3600
CLIPFLOW_DOWNLOAD_ALLOWED_HOSTS=
CLIPFLOW_IMPORT_MAX_CONCURRENT=2
# Cookies opsional untuk video privat milik sendiri (jangan commit file ini):
CLIPFLOW_YTDLP_COOKIES=
```

Catatan:

- `CLIPFLOW_DOWNLOAD_ALLOWED_HOSTS` kosong berarti semua http/https diizinkan. Isi mis. `youtube.com,youtu.be` untuk membatasi.
- Host lokal/IP privat selalu ditolak (proteksi SSRF, diatur `CLIPFLOW_DOWNLOAD_DNS_CHECK`).
- Mengunduh dari platform pihak ketiga dapat melanggar ketentuan layanan mereka. Gunakan terutama untuk konten Anda sendiri, dan wajibkan konfirmasi hak di UI.

### 3.8 Seleksi klip AI: Ollama atau 9router

Driver AI dapat dipilih tanpa mengubah kode. Dua pilihan didukung:

**A. 9router / gateway OpenAI-compatible (mis. `http://localhost:20128/v1`)**

```dotenv
CLIPFLOW_AI_DRIVER=9router
CLIPFLOW_AI_BASE_URL=http://localhost:20128/v1
CLIPFLOW_AI_MODEL=assist
CLIPFLOW_AI_API_KEY=
CLIPFLOW_AI_RESPONSE_FORMAT=true
CLIPFLOW_AI_TIMEOUT=600
```

Buat API key di dashboard 9router (`http://localhost:20128`), lalu isi `CLIPFLOW_AI_API_KEY` (jangan simpan key di pengaturan database atau commit ke Git). Setelah mengisi:

```powershell
php artisan config:clear
php artisan clipflow:ai-test
```

`CLIPFLOW_AI_BASE_URL` harus menyertakan `/v1` karena aplikasi memanggil `/v1/chat/completions` dan `/v1/models`.

**B. Ollama lokal**

```dotenv
CLIPFLOW_AI_DRIVER=ollama
CLIPFLOW_AI_BASE_URL=http://127.0.0.1:11434
CLIPFLOW_AI_MODEL=llama3.1:8b
```

```powershell
ollama pull llama3.1:8b
ollama serve
```

Pengaturan driver, base URL, dan model juga dapat diubah dari halaman **Pengaturan** di aplikasi, termasuk tombol **Tes koneksi**. `php artisan clipflow:check` menampilkan driver aktif, endpoint, dan status keterjangkauannya.

### 3.9 Batas upload PHP

Default PHP sering hanya `2M/8M`. Untuk file hingga 2 GB, ubah `php.ini` (Laragon → Menu → PHP → php.ini, atau menu PHP versi):

```ini
upload_max_filesize = 2048M
post_max_size = 2048M
max_execution_time = 600
memory_limit = 512M
```

Restart web server / `php artisan serve`. Batas aplikasi sendiri diatur oleh:

```dotenv
CLIPFLOW_MAX_UPLOAD_BYTES=2147483648
CLIPFLOW_MAX_DURATION_SECONDS=7200
```

Livewire juga dibatasi 2 GB; lihat `config/livewire.php` (`temporary_file_upload.rules`). Naikkan bila perlu. `php artisan clipflow:check` memperingatkan bila batas PHP lebih kecil.

---

## 4. Menjalankan aplikasi

Buka beberapa terminal di folder proyek. PC harus tetap menyala agar otomatisasi berjalan.

**1) Web server** (pilih salah satu)

```powershell
php artisan serve --host=127.0.0.1 --port=8000
# atau akses lewat virtual host Laragon, mis. http://clipflow.test
```

**2) Vite** (hanya saat pengembangan)

```powershell
npm run dev
```

**3) Queue worker pipeline** (probe, transkripsi, seleksi AI, render)

```powershell
php artisan queue:work --queue=pipeline --timeout=7200 --tries=1
```

**4) Queue worker upload** (YouTube)

```powershell
php artisan queue:work --queue=uploads --timeout=3600 --tries=1
```

**5) Scheduler** (dispatch upload jatuh tempo, rekonsiliasi, heartbeat, retensi)

```powershell
php artisan schedule:work
```

Untuk produksi gunakan Supervisor + cron, lihat `docs/deployment-vps.md`.

### Autostart worker di Windows

Agar tidak perlu membuka lima terminal setiap kali, gunakan script yang disediakan:

```powershell
# Jalankan worker pipeline, worker upload, dan scheduler dengan auto-restart
powershell -ExecutionPolicy Bypass -File .\scripts\start-clipflow.ps1
```

Untuk menyalakan otomatis saat Anda login ke Windows:

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\install-autostart.ps1 -StartNow
# hapus autostart:
powershell -ExecutionPolicy Bypass -File .\scripts\install-autostart.ps1 -Remove
```

Setiap kali kode atau `.env` berubah, **restart worker** agar config/code baru dimuat:

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\restart-clipflow.ps1
```

Log worker ditulis ke `storage/logs/worker-pipeline.out.log`, `worker-uploads.out.log`, dan `scheduler.out.log`.

Autostart dipasang di folder **Startup** Windows (tanpa admin) sebagai `clipflow-workers.cmd`, sehingga worker mulai otomatis saat Anda login.

Dashboard menampilkan status **Worker aktif / aktif · memproses job / idle / tidak aktif**. Worker dianggap aktif jika ada job yang sedang di-reserve atau heartbeat masih segar. Status “tidak aktif” hanya muncul bila ada job menunggu tetapi tidak ada worker yang mengambilnya — jadi worker idle tidak salah dituduh mati.

Penting untuk database queue: `DB_QUEUE_RETRY_AFTER=9000` harus lebih besar dari timeout job terpanjang. Jika Anda menaikkan timeout transkripsi, naikkan juga `retry_after` agar job tidak dikerjakan ganda.

---

## 5. Alur pemakaian

1. **Sumber Video → Tambah sumber** → unggah MP4/MOV/WebM **atau tempel link video** (YouTube/situs lain via yt-dlp), lalu konfirmasi hak penggunaan.
2. Worker menjalankan: unduh link (bila impor) → FFprobe → ekstraksi audio → faster-whisper → seleksi AI (driver aktif) → render tiap klip.
3. **Review Klip** → tonton preview, edit judul/caption, pilih channel + privasi + deklarasi anak → Approve / Reject.
4. Jika **auto-schedule** aktif (default nonaktif), approval langsung mencari slot. Jika nonaktif, tekan **Jadwalkan slot berikutnya** di detail klip.
5. **Jadwal Upload** memantau antrean, memungkinkan jeda/lanjut, pembatalan sebelum upload, dan pemeriksaan status upload yang belum pasti.
6. **Channel YouTube** menghubungkan channel via OAuth resmi. Upload hanya berjalan bila channel terhubung.
7. **Aktivitas** menyimpan jejak tindakan penting.

Status pipeline dan aturan transisi lengkap: `docs/pipeline.md`.

### Template subtitle & render ulang

Subtitle dibakar memakai format ASS dengan ukuran piksel nyata dan posisi yang dihitung dari **tepi area video** (bukan dasar frame), sehingga tidak jatuh di padding hitam.

Template dikelola di sidebar **Template Subtitle**:

- Nama template.
- Font, ukuran (10–72 px), bold.
- Warna teks, warna & tebal outline, shadow.
- Warna background + opasitas (opsional, untuk gaya kotak).
- Posisi: atas / tengah / bawah, selalu di dalam area video.
- Margin samping dan jarak dari tepi video.
- Maksimal karakter per baris dan maksimal baris (teks panjang otomatis diperkecil agar tetap muat).
- Pratinjau langsung yang ikut berubah saat field diubah.
- Preset cepat: **Minimal**, **Bold Creator**, **Highlight**, **Podcast**.
- Daftar template: edit, duplikat, jadikan default, hapus.

Urutan pemakaian template (prioritas tertinggi lebih dulu):

```text
1. Template khusus klip  (di halaman detail review)
2. Template sumber       (di halaman detail sumber)
3. Template default      (Default Clipflow)
```

Template per sumber diatur di halaman detail sumber. Template per klip diatur di halaman review dengan tombol **Terapkan** (otomatis menjadwalkan render ulang).

Template default bernama **Default Clipflow** (30 px, putih, outline hitam, posisi bawah).

Klip yang sudah dirender tidak otomatis berubah. Render ulang lewat:

- tombol **Render ulang subtitle** di detail review, atau
- perintah:
  ```powershell
  php artisan clipflow:rerender-subtitles --status=review --limit=100
  php artisan clipflow:rerender-subtitles --status=failed --limit=100
  ```

Restart worker setelah mengubah kode/config agar renderer baru dipakai:

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\restart-clipflow.ps1
```

---

## 6. Aturan approval dan penjadwalan

- Approval memerlukan klip berstatus **review**, sudah punya file render + hash media, channel terhubung, privasi, dan deklarasi konten anak.
- `content_hash` = hash dari judul, caption, path render, dan hash media. Approval menyimpan versi + hash + channel + privasi + jadwal.
- Mengubah judul/caption setelah approval akan:
  - membatalkan approval,
  - membatalkan jadwal (jika belum upload),
  - mengembalikan klip ke **review**.
- Approve berulang dengan pengaturan sama bersifat idempotent; tidak membuat approval atau jadwal ganda.
- Slot memakai Asia/Jakarta, default 08.00–22.30 setiap 30 menit (30 slot). Slot ke-31 pindah hari berikutnya.
- Pause mencegah upload baru. Job yang sudah berjalan tidak dipaksa berhenti.
- Backlog yang terlalu lama (melewati `CLIPFLOW_OVERDUE_GRACE_MINUTES`, default 120 menit) digeser ke slot berikutnya, bukan diunggah sekaligus.
- Maksimum dispatch per putaran dan upload bersamaan dapat diatur (`CLIPFLOW_MAX_CONCURRENT_UPLOADS`, `CLIPFLOW_DISPATCHES_PER_RUN`).

Status publikasi dipisah: `scheduled`, `uploading`, `uploaded`, `failed`, `needs_reconciliation` (plus `cancelled`). **`uploaded` berarti API YouTube sudah mengembalikan ID video** — bukan jaminan video sudah publik atau selesai diproses YouTube.

---

## 7. YouTube OAuth

Panduan langkah demi langkah ada di `docs/youtube-oauth.md`. Ringkasnya:

1. Buat project Google Cloud, aktifkan **YouTube Data API v3**.
2. Konfigurasikan OAuth consent screen dan tambahkan akun penguji.
3. Buat OAuth Client ID tipe **Web application**.
4. Tambahkan redirect URI persis: `http://localhost:8000/channels/youtube/callback` (atau `APP_URL` Anda).
5. Isi `.env`:

```dotenv
GOOGLE_CLIENT_ID=...
GOOGLE_CLIENT_SECRET=...
GOOGLE_REDIRECT_URI="${APP_URL}/channels/youtube/callback"
```

6. `php artisan config:clear`, lalu buka **Channel YouTube → Hubungkan**.

Scope minimum: `youtube.upload` dan `youtube.readonly`. Aplikasi tidak pernah meminta password Google. Token disimpan terenkripsi (`encrypted` cast) dan tidak dimasukkan ke respons/frontend/log.

Jika kredensial belum ada, UI menunjukkan **Belum terhubung** dan tombol koneksi dinonaktifkan. Tidak ada klaim upload nyata.

---

## 8. Operasional

### Kuota YouTube

YouTube Data API default memberi **10.000 unit/hari**, sedangkan satu upload (`videos.insert`) memakai **1.600 unit**:

```text
10.000 / 1.600 ≈ 6 upload per hari
```

Aplikasi menghitung batas harian efektif = nilai terkecil antara batas di Pengaturan dan kapasitas kuota. Nilai dapat diubah di **Pengaturan → Kuota YouTube** atau `.env`:

```dotenv
YOUTUBE_DAILY_QUOTA_UNITS=10000
YOUTUBE_UPLOAD_COST_UNITS=1600
YOUTUBE_QUOTA_RESERVE_UNITS=200
```

Jika kuota tidak cukup, job upload digeser ke hari berikutnya, bukan diulang buta. Untuk target 30 upload/hari, ajukan **quota extension** ke Google; tanpa itu kapasitas nyata ±6/hari.

### Notifikasi Telegram

Notifikasi dikirim saat klip siap review, pipeline gagal, upload berhasil, gagal, atau perlu pemeriksaan.

```dotenv
CLIPFLOW_TELEGRAM_ENABLED=true
TELEGRAM_BOT_TOKEN=123456:ABC...
TELEGRAM_CHAT_ID=123456789
```

Buat bot via `@BotFather`, lalu kirim pesan ke bot agar chat ID muncul. Uji dari **Pengaturan → Notifikasi Telegram → Tes kirim**. Jika gagal dikirim, notifikasi tidak menggagalkan pipeline (hanya dicatat di log).

### Job gagal

Halaman **Job Gagal** (`/failed-jobs`) menampilkan job yang gagal beserta pesan error. Anda dapat **Retry** atau **Hapus** dari UI. Retry hanya berguna jika penyebabnya sudah diperbaiki; worker pipeline harus aktif.

---

## 9. Keamanan & keandalan

- Autentikasi wajib untuk semua halaman dan aksi Livewire; tidak ada registrasi publik.
- Policy per model untuk sumber, klip, job, dan channel.
- File media di `storage/app/private`, preview/download lewat controller ber-otorisasi + CSRF untuk aksi tulis.
- FFmpeg/FFprobe/Python dijalankan dengan array argumen (`Symfony Process`), tanpa shell interpolation.
- Path file internal memakai UUID; nama file pengguna tidak dipakai sebagai path eksekusi.
- Token OAuth dienkripsi; access/refresh token disembunyikan dari serialisasi.
- Queue: `tries=1` untuk job berat + klaim atomik + unique constraint (approval per job, slot per channel/waktu, approval version).
- Error upload dikategorikan: kuota, auth, validasi, jaringan, konflik, belum pasti. Hanya jaringan/unknown yang boleh diulang otomatis dengan backoff terbatas.
- Aktivitas penting dicatat; retensi default nonaktif (0 hari) dan command retensi tidak menghapus file yang masih dipakai antrean.

---

## 10. Pengujian

```powershell
php artisan test --compact
vendor/bin/pint --format agent
```

Hasil terakhir: **93 tes lulus, 292 assertion**.

Mencakup antara lain: akses tanpa login, validasi upload, impor link (validasi URL, proteksi SSRF, mock yt-dlp sukses/gagal, redaksi query URL), driver AI (pemilihan client, request bearer + JSON mode, kode-fence, auth tanpa kebocoran API key, toleransi chunk gagal), kuota YouTube (kapasitas, batas efektif, penjadwalan, penundaan upload), heartbeat worker, notifikasi Telegram (payload, escaping, kegagalan tidak melempar), halaman job gagal, timestamp AI invalid, approval wajib render, approval wajib sebelum upload, perubahan konten membatalkan approval, approve berulang tidak menggandakan jadwal, slot ke-31 pindah hari, jadwal tidak di masa lalu, pause mencegah upload, batas harian dengan zona waktu, concurrent job tidak menggandakan upload, timeout tidak memicu retry buta, token OAuth tidak tampil di respons, render halaman & komponen, serta Range download.

Detail cakupan dan bagian yang **belum** diuji end-to-end: `docs/testing.md`.

---

## 11. Keterbatasan yang perlu diketahui

- FFmpeg, yt-dlp, dan model Whisper tidak disertakan; lingkungan ini belum memilikinya. Upload file tetap bisa dipakai tanpa yt-dlp, dan pipeline menampilkan error yang bisa dipahami bila tool belum siap. Seleksi AI tetap memerlukan kredensial Ollama atau 9router.
- Uji end-to-end ke akun YouTube nyata **belum dilakukan** karena tidak ada kredensial produksi. Jangan menganggap adapter terverifikasi penuh sebelum mencoba dengan channel uji private.
- Belum ada face tracking, auto-reframe cerdas, atau penghapusan silence. Layout memakai fit + padding.
- Deteksi batas kalimat mengandalkan timestamp segmen Whisper; hasil transkripsi yang buruk dapat menurunkan kualitas potongan.
- OAuth app dalam status Testing dapat membuat refresh token kedaluwarsa dalam 7 hari (kebijakan Google); hubungkan ulang bila terjadi. Verifikasi aplikasi diperlukan untuk penggunaan publik.
- Upload 2 GB melalui PHP built-in server tidak disarankan; gunakan Nginx/Apache Laragon atau VPS.
- Satu owner/satu channel YouTube per workspace pada fase ini.
- Kuota YouTube Data API default hanya mendukung ±6 upload/hari (10.000 unit/hari, 1.600 unit per upload). Aplikasi menampilkan kapasitas ini dan menggeser upload ke hari berikutnya; target 30/hari memerlukan quota extension dari Google.
- Notifikasi Telegram dan heartbeat worker bergantung pada scheduler + worker yang berjalan. Jika semua proses dimatikan, dashboard akan menandai worker tidak aktif.
- Retensi file tidak otomatis menghapus data demo.

---

## 12. Struktur penting

```
app/
  Console/Commands/     # clipflow:check, ai-test, worker-ping, create-owner, demo, dispatch-uploads, reconcile, prune
  Services/Ai/          # driver AI: Ollama dan OpenAI-compatible (9router)
  Services/             # ... + YouTubeQuotaService, TelegramNotifier, ClipflowNotifier
  Enums/                # status eksplisit
  Jobs/                 # probe, transcribe, select, render, upload
  Livewire/             # dashboard, upload, review, jadwal, channel, template subtitle, settings
  Models/               # domain + relasi
  Policies/             # otorisasi kepemilikan
  Services/             # probe, transkripsi, seleksi AI, render, jadwal, approval, YouTube
database/
  migrations/           # skema domain
  factories/            # data uji
  seeders/              # owner lokal + DemoSeeder terpisah
docs/                   # pipeline, OAuth, deployment, testing
scripts/transcribe.py   # faster-whisper
scripts/start-clipflow.ps1      # autostart worker Windows
scripts/restart-clipflow.ps1    # restart worker setelah deploy
scripts/install-autostart.ps1   # daftar/hapus autostart Startup
resources/views/        # Blade + Tailwind
```

---

## 13. Deployment VPS

Lihat `docs/deployment-vps.md`. Ringkas: Nginx + PHP-FPM + MySQL + Node build, dua worker Supervisor, cron `schedule:run`, HTTPS, `APP_DEBUG=false`, backup database, dan kapasitas CPU/RAM yang cukup untuk Whisper/FFmpeg.
