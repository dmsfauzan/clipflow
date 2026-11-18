# Setup Google OAuth YouTube

1. Buka Google Cloud Console dan buat project khusus Clipflow.
2. Enable **YouTube Data API v3**.
3. Configure OAuth consent screen. Untuk pengujian lokal, tambahkan akun Google sebagai Test User.
4. Create Credentials → OAuth client ID → Web application.
5. Tambahkan redirect URI persis, misalnya:

   ```text
   http://localhost:8000/channels/youtube/callback
   ```

   Bila memakai domain Laragon, gunakan `APP_URL` yang sama persis dengan domain tersebut.
6. Isi `.env` dan jangan commit nilainya:

   ```dotenv
   GOOGLE_CLIENT_ID=...
   GOOGLE_CLIENT_SECRET=...
   GOOGLE_REDIRECT_URI="${APP_URL}/channels/youtube/callback"
   ```

7. Jalankan `php artisan config:clear`, buka **Channel YouTube**, dan klik **Hubungkan**.

## Scope

Clipflow meminta:

- `https://www.googleapis.com/auth/youtube.upload`
- `https://www.googleapis.com/auth/youtube.readonly`

Scope dipakai untuk upload video dan membaca identitas channel. Password Google tidak pernah diminta.

## Token

Access token dan refresh token disimpan dengan encrypted Eloquent cast dalam database. Token tidak dikirim ke Blade/Livewire, tidak ditulis di URL, dan tidak dicatat ke activity log. Disconnect menghapus token.

## Privacy dan kids declaration

Default privacy `private`. Operator memilih `private`, `unlisted`, atau `public` pada review dan wajib memilih deklarasi dibuat untuk anak. Konfigurasi publikasi disimpan pada approval dan diverifikasi ulang sebelum upload.

## Produksi

OAuth consent screen mungkin memerlukan verification sebelum dapat dipakai oleh pengguna umum. Untuk pengujian lokal, gunakan private video dan akun penguji. YouTube quota error tidak boleh dipaksa retry; job akan berstatus failed dengan kategori quota.
