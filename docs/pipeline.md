# Pipeline dan state transition

## Sumber video

```text
uploaded -> probing -> transcribing -> selecting -> ready
   \                                      |
    --------------- failed <--------------

queued -> downloading -> (menjadi uploaded) -> probing -> ...
```

- `queued`: sumber dari link menunggu job `DownloadRemoteSource` (queue `pipeline`).
- `downloading`: yt-dlp mengunduh ke `storage/app/private/imports/{id}`; progres disimpan di `import_progress` dan ditampilkan di halaman detail.
- `uploaded`: file sudah tersimpan privat; job `ProbeVideoSource` menunggu worker.
- `probing`: FFprobe memvalidasi duration, video stream, audio stream, container/codec.
- `transcribing`: FFmpeg mengekstrak WAV 16 kHz mono, lalu `scripts/transcribe.py` menjalankan faster-whisper.
- `selecting`: transkrip dipecah menjadi chunk dengan timestamp absolut; Ollama mengembalikan kandidat JSON.
- `ready`: semua klip selesai dirender; subtitle memakai template aktif (prioritas: template klip → template sumber → template default) dengan ukuran piksel, posisi dan margin dari tepi area video, sehingga tidak jatuh di padding hitam.
- `failed`: error terminal yang dicatat pada sumber. Operator dapat mencoba ulang dari UI.

## Klip

```text
rendering -> review -> approved -> scheduled -> uploading -> uploaded
    |          |          |           |            |
    +------> failed   rejected <-------+--------> failed
```

Klip baru masuk `review` hanya setelah file MP4, thumbnail, SRT, hash, dan metadata render berhasil disimpan. Judul/caption yang berubah setelah approval mencabut approval dan mengembalikan status ke `review`.

## Publication job

```text
scheduled -> uploading -> uploaded
      |          |
      |          +--> failed (aman diulang untuk network/unknown, terbatas)
      |          +--> needs_reconciliation (timeout/koneksi putus setelah request)
      +--> cancelled (operator membatalkan sebelum upload)
```

`uploaded` hanya berarti API mengembalikan ID video. `needs_reconciliation` tidak boleh diulang buta: gunakan **Periksa status**, lalu lanjutkan sesi resumable yang sama bila aman.

## Kuota API

Batas harian efektif = nilai terkecil antara batas upload di Pengaturan dan kapasitas kuota YouTube (`unit harian / biaya per upload`). Default 10.000 unit/hari dan 1.600 unit per upload menghasilkan kapasitas ±6 upload/hari. Job yang tidak mendapat kuota digeser ke hari berikutnya dan tidak melakukan request upload.

- Cakupan situs: semua situs yang didukung yt-dlp, dengan proteksi SSRF dan opsi `CLIPFLOW_DOWNLOAD_ALLOWED_HOSTS`. Video privat milik sendiri dapat diimpor dengan `CLIPFLOW_YTDLP_COOKIES`.
- Progres unduhan disimpan sebagai persen (`import_progress`) dan diperbarui oleh job di queue `pipeline`, bukan di HTTP request.
- Seleksi klip memakai driver AI aktif (`CLIPFLOW_AI_DRIVER`): `ollama` memanggil `/api/chat`, `9router` memanggil `/v1/chat/completions` dengan API key dari `.env`. Validasi timestamp/overlap tidak berubah untuk kedua driver.

## Batas dan validasi AI- Kandidat harus punya `start/end` numerik, berada dalam durasi sumber, durasi 15–60 detik (configurable), dan dekat dengan batas segmen Whisper.
- Isi transkrip dimasukkan sebagai data dengan instruksi eksplisit agar prompt injection dari konten tidak menjadi instruksi agent.
- Kandidat overlap > 0,5 detik ditolak; maksimal 30 kandidat per sumber.
- Skor AI adalah heuristik; UI tidak menyebutnya prediksi viral.
