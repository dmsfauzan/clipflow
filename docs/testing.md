# Testing dan batas E2E

## Menjalankan

```powershell
php artisan test --compact
vendor/bin/pint --format agent
```

Test suite mengisolasi database dengan SQLite in-memory dan memakai fake/mock untuk HTTP, queue, storage, dan layanan Ollama. Test mencakup:

- autentikasi, policy, media privat, dan Range response;
- validasi format/ukuran/hak upload;
- impor link: validasi URL, penolakan host privat (SSRF), batas impor bersamaan, mock yt-dlp sukses/gagal, redaksi query rahasia di log;
- driver AI: pemilihan client dari config, request `Bearer` + `response_format: json_object`, toleransi code fence, error auth/kuota/server, dan API key tidak bocor ke pesan error;
- seleksi AI: chunk yang gagal tidak menggagalkan seluruh sumber selama ada chunk lain yang berhasil;
- kuota YouTube: kapasitas dari unit/biaya upload, batas harian efektif, penjadwalan yang mentok kuota, dan upload yang digeser saat kuota habis;
- worker heartbeat: job heartbeat memperbarui setting, command ping mengirim job, dan status worker membedakan idle / memproses / tidak aktif;
- ketahanan pipeline: default setting tetap aman bila key config hilang, dan kegagalan seleksi AI menandai sumber `failed` (tidak tertahan di `selecting`);
- notifikasi Telegram: no-op saat nonaktif, payload chat_id/text saat aktif, escaping judul, kegagalan HTTP tidak melempar;
- halaman job gagal: daftar, hapus, dan validasi UUID;
- render: direktori output dibuat otomatis, SRT relatif terhadap awal klip, ASS memakai ukuran piksel, margin subtitle dihitung dari tepi bawah video, dan kegagalan thumbnail tidak menggagalkan render;
- template subtitle: posisi atas/tengah/bawah, warna teks/outline, background+opasitas, margin, pembungkusan teks, serta CRUD template (buat, edit, duplikat, default, hapus);
- template subtitle lanjutan: preset cepat, override per sumber, override per klip (prioritas klip > sumber > default), dan penolakan perubahan template pada klip approved;
- render ulang subtitle: klip review bisa dijadwalkan ulang, klip approved ditolak, command massal berjalan;
- validasi JSON/timestamp/overlap/durasi AI;
- state approval, idempotensi, revocation setelah edit;
- slot masa depan, batas harian Asia/Jakarta, slot ke-31, cancellation;
- pause queue, dispatch backlog terbatas, atomic claim;
- status upload sukses, error quota/auth/network/uncertain, resumable session;
- OAuth state, token encryption dan token tidak masuk response;
- rendering route dan Livewire component.

## Belum diuji end-to-end

- Upload video nyata ke YouTube, karena tidak boleh menggunakan akun produksi untuk test suite.
- FFmpeg render nyata pada berbagai codec/container di mesin ini, karena FFmpeg belum tersedia pada PATH saat implementasi.
- faster-whisper transkripsi nyata dan download model.
- Ollama inference nyata dan kualitas editorial kandidat.
- Upload file 2 GB melalui web server lokal; konfigurasi `php.ini` dan reverse proxy harus disesuaikan.

Gunakan channel YouTube uji, privasi `private`, dan video yang memang Anda miliki untuk pengujian manual.
