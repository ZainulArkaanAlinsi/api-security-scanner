# Deploy

Aplikasi ini butuh **tiga proses** dari satu image yang sama:

| Proses | Perintah | Tugas |
|---|---|---|
| web | `frankenphp php-server --root /app/public --listen :8080` | melayani halaman |
| worker | `php artisan queue:work` | mengerjakan scan |
| scheduler | `php artisan schedule:work` | memicu monitoring tiap 6 jam |

Tanpa worker, ticket akan berhenti di status "Sedang scan". Karena itu platform yang hanya menjalankan satu proses web (Vercel, Netlify, shared hosting biasa) tidak cocok.

> Mencari opsi **gratis**? Railway dan Fly.io sama-sama berbayar sejak tier gratisnya dihentikan. Untuk VM gratis selamanya, lihat **[DEPLOY-ORACLE.md](DEPLOY-ORACLE.md)**.

---

## Railway (paling mudah)

Railway menyediakan MySQL dan bisa menjalankan beberapa service dari satu repo.

1. **Buat project** → *Deploy from GitHub repo* → pilih repo ini. Railway mendeteksi `Dockerfile` otomatis.
2. **Tambah database**: *New* → *Database* → *MySQL*.
3. **Isi variabel** di service web (Variables):

   ```
   APP_NAME=API Scanner
   APP_ENV=production
   APP_DEBUG=false
   APP_URL=https://<domain-railway-kamu>
   APP_LOCALE=id
   APP_TIMEZONE=Asia/Jakarta
   LOG_CHANNEL=stderr

   DB_CONNECTION=mysql
   DB_HOST=${{MySQL.MYSQLHOST}}
   DB_PORT=${{MySQL.MYSQLPORT}}
   DB_DATABASE=${{MySQL.MYSQLDATABASE}}
   DB_USERNAME=${{MySQL.MYSQLUSER}}
   DB_PASSWORD=${{MySQL.MYSQLPASSWORD}}

   SESSION_DRIVER=database
   SESSION_SECURE_COOKIE=true
   QUEUE_CONNECTION=database
   CACHE_STORE=database
   SCANNER_ALLOW_PRIVATE=false
   ```

4. **Buat `APP_KEY`** di komputer lalu tempel hasilnya sebagai variabel:

   ```bash
   php artisan key:generate --show
   ```

5. **Jalankan migration** sekali lewat Railway CLI atau shell service:

   ```bash
   php artisan migrate --force
   ```

6. **Tambah dua service lagi** dari repo yang sama, dengan *Custom Start Command*:
   - `php artisan queue:work --tries=1 --timeout=90 --sleep=3`
   - `php artisan schedule:work`

   Salin variabel yang sama ke keduanya (Railway bisa share variables antar service).

---

## Fly.io

Fly menjalankan ketiga proses dari satu `fly.toml` lewat process groups.

```bash
fly launch --no-deploy --copy-config      # ganti nama app di fly.toml
fly postgres create                        # atau pakai database eksternal
fly postgres attach <nama-db>              # mengisi DATABASE_URL otomatis
```

Karena Fly menyediakan Postgres (bukan MySQL), set koneksinya:

```bash
fly secrets set \
  APP_KEY="$(php artisan key:generate --show)" \
  APP_URL="https://<app>.fly.dev" \
  DB_CONNECTION=pgsql
```

Lalu deploy:

```bash
fly deploy
```

`release_command` di `fly.toml` menjalankan `php artisan migrate --force` otomatis setiap deploy. Cek ketiga proses berjalan:

```bash
fly status
fly logs -a <app>
```

---

## Coba dulu di komputer sendiri

Butuh Docker Desktop:

```bash
cp .env.example .env
docker compose up --build

docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate
docker compose exec app php artisan db:seed --class=DemoSeeder
```

Buka http://localhost:8080. Compose menjalankan web, worker, scheduler, dan MySQL sekaligus — susunan yang sama seperti di production.

---

## Setelah deploy, cek ini

- [ ] `/up` mengembalikan 200
- [ ] Bisa register dan login
- [ ] Scan satu endpoint publik, hasilnya muncul dalam beberapa detik (kalau berhenti di "Sedang scan", berarti worker tidak jalan)
- [ ] Log worker menunjukkan job `ScanTicketJob ... DONE`
- [ ] Email reset password terkirim (isi konfigurasi SMTP; `MAIL_MAILER=log` hanya menulis ke log)
- [ ] Halaman error menampilkan tampilan kustom, bukan stack trace — kalau muncul stack trace, `APP_DEBUG` masih `true`

## Catatan

- **`APP_URL` wajib benar.** Nilai ini dipakai untuk menyusun tautan di email reset password, pesan Slack/Discord, link laporan publik, dan URL badge. Kalau masih `http://localhost`, semua tautan itu tidak bisa diklik penerimanya.
- **Email**: `MAIL_MAILER=log` tidak mengirim apa pun. Pakai SMTP seperti Mailtrap, Resend, atau Brevo.
- **Slack/Discord**: webhook diatur per pengguna dari halaman Profil, bukan lewat `.env`.
- **Sertifikat CA**: image Linux sudah membawa CA bundle, jadi `SCANNER_CA_BUNDLE` biarkan kosong. Variabel itu hanya diperlukan di Windows/Laragon.
- **Jangan** menjalankan `DemoSeeder` di production, karena password akun demonya tertulis publik di README.
