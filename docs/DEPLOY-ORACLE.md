# Deploy ke Oracle Cloud (Always Free)

Oracle memberi VM ARM **gratis selamanya** — bukan trial. Jatah Always Free: 4 core Ampere A1 dan 24 GB RAM yang bisa dipakai untuk satu atau beberapa VM. Aplikasi ini cukup nyaman dengan 1 core / 6 GB.

Kartu kredit tetap diminta **untuk verifikasi identitas**, tapi resource Always Free tidak ditagih. Pastikan saat membuat VM kamu memilih shape yang bertanda **"Always Free eligible"**.

---

## 1. Buat VM (kamu yang lakukan)

1. Daftar di [cloud.oracle.com](https://cloud.oracle.com) — pilih region terdekat (Singapore atau Osaka untuk Indonesia). **Region tidak bisa diubah setelah dipilih.**
2. *Compute* → *Instances* → **Create instance**
3. Atur:
   - **Image**: Ubuntu 24.04 (atau 22.04)
   - **Shape**: *Ampere* → `VM.Standard.A1.Flex` → 1 OCPU, 6 GB RAM → pastikan muncul label **Always Free eligible**
   - **SSH key**: unggah kunci publikmu, atau biarkan Oracle membuatkan lalu simpan file privat-nya
4. Setelah instance jalan, catat **Public IP**.
5. **Buka port 80 dan 443**: *Instance* → klik nama *Subnet* → *Security List* → **Add Ingress Rules**:

   | Source CIDR | IP Protocol | Destination Port |
   |---|---|---|
   | `0.0.0.0/0` | TCP | `80` |
   | `0.0.0.0/0` | TCP | `443` |

   Langkah ini **sering terlewat**. Kalau nanti situsnya tidak bisa dibuka padahal container jalan, biasanya ini penyebabnya.

> Kalau shape Ampere menampilkan "out of capacity", coba region atau availability domain lain, atau ulangi beberapa jam kemudian. Kapasitas ARM gratis memang sering penuh.

## 2. Masuk ke VM

```bash
ssh -i /path/ke/kunci-privat ubuntu@<PUBLIC_IP>
```

## 3. Jalankan skrip setup

```bash
curl -fsSL https://raw.githubusercontent.com/ZainulArkaanAlinsi/api-security-scanner/main/deploy/setup-oracle.sh -o setup.sh
less setup.sh      # baca dulu isinya sebelum menjalankan skrip dari internet
bash setup.sh
```

Skrip itu akan:
- memasang Docker
- membuka port 80/443 di firewall VM (aturan iptables bawaan Oracle memblokir semuanya)
- meng-clone repo ini
- membuat `.env` dengan `APP_KEY` baru dan password database acak
- menjalankan web, queue worker, scheduler, dan MySQL lewat Docker Compose
- menjalankan migration

Build pertama memakan waktu beberapa menit karena image dibangun langsung di VM.

Setelah selesai, buka `http://<PUBLIC_IP>` dan daftar akun pertamamu.

## 4. Pasang HTTPS (disarankan)

Tanpa HTTPS, aplikasimu sendiri akan dapat temuan **high** kalau kamu scan dirinya sendiri — lucu untuk demo, tapi sebaiknya diperbaiki.

Butuh nama domain. Gratis lewat [DuckDNS](https://www.duckdns.org): buat subdomain, arahkan ke Public IP VM, lalu:

```bash
cd ~/api-security-scanner
rm .env                                    # agar skrip membuat ulang dengan domain
DOMAIN=namamu.duckdns.org bash ~/setup.sh
```

FrankenPHP mengurus sertifikat Let's Encrypt sendiri, tanpa certbot. Pastikan domain sudah mengarah ke IP sebelum menjalankan, karena Let's Encrypt memverifikasi lewat port 80.

---

## Perintah harian

```bash
cd ~/api-security-scanner
C=deploy/docker-compose.prod.yml

docker compose -f $C ps                    # status semua service
docker compose -f $C logs -f app           # log web
docker compose -f $C logs -f worker        # log scan
docker compose -f $C restart worker

git pull && docker compose -f $C up -d --build   # deploy versi terbaru
docker compose -f $C exec app php artisan migrate --force
```

## Kalau ada masalah

| Gejala | Penyebab yang paling sering |
|---|---|
| Situs tidak bisa dibuka sama sekali | Ingress rule 80/443 belum ditambahkan di Security List Oracle |
| Ticket berhenti di "Sedang scan" | Service `worker` mati — cek `docker compose -f $C logs worker` |
| Halaman error menampilkan stack trace | `APP_DEBUG` masih `true` di `.env` |
| Scan selalu gagal "tidak bisa terhubung" | Semua koneksi keluar VM diblokir; cek egress rule di Security List |
| Sertifikat HTTPS gagal terbit | Domain belum mengarah ke IP VM, atau port 80 tertutup |
| Tautan di email atau Slack tidak bisa diklik | `APP_URL` di `.env` masih `http://localhost`, bukan domain atau IP sebenarnya |

## Backup database

```bash
docker compose -f deploy/docker-compose.prod.yml exec db \
  mysqldump -u root -p"$(grep ^DB_PASSWORD .env | cut -d= -f2)" api_scanner > backup-$(date +%F).sql
```

## Catatan keamanan

- Jangan jalankan `DemoSeeder` di server publik — password akun demonya tertulis di README.
- Scanner hanya boleh dipakai untuk endpoint milikmu sendiri atau yang kamu punya izin mengujinya. VM ini beralamat IP atas namamu, dan komplain penyalahgunaan akan tertuju ke akun Oracle-mu.
- Biarkan `SCANNER_ALLOW_PRIVATE=false`. Kalau diaktifkan di server publik, siapa pun yang punya akun bisa memakai scanner untuk mengintip jaringan internal cloud-mu.
