# WhatsApp Gateway

Gateway Go untuk WhatsApp Business Cloud API, OTP, dashboard admin, webhook pesan masuk, balasan AI opsional, dan antrean SQLite. Production hanya menerima mode resmi `WHATSAPP_MODE=cloud`. Klien WhatsApp Web/QR (`whatsmeow`) dibatasi untuk eksperimen non-production.

## Persiapan production

1. Siapkan akun WhatsApp Business Platform, nomor bisnis, access token, App Secret, webhook, dan template AUTHENTICATION OTP yang sudah disetujui Meta. Template OTP memakai kode pada body dan tombol OTP; bila template menampilkan masa berlaku, samakan dengan `OTP_EXPIRY_MINUTES`.
2. Salin `.env.example` menjadi `.env`, isi kredensial lewat secret manager atau editor lokal, lalu kunci akses file:

   ```sh
   cp .env.example .env
   chmod 600 .env
   openssl rand -hex 32
   htpasswd -nBC 12 admin
   ```

   Masukkan hasil acak ke `GATEWAY_SECRET`. Hasil `htpasswd` menampilkan `admin:<bcrypt-hash>`; salin nilai setelah titik dua ke `ADMIN_PASSWORD_HASH`. Perintah meminta kata sandi secara interaktif. Jangan taruh kata sandi dalam argumen shell atau chat.

3. Isi nilai `WHATSAPP_*`, termasuk versi Graph API yang masih didukung, serta nama template default dan reset. `WHATSAPP_OTP_TEMPLATE_*` adalah nama template Meta, bukan teks isi pesan. Atur callback Meta ke `https://<domain>/webhooks/whatsapp`, aktifkan subscription `messages`, dan gunakan token verifikasi serta App Secret yang sama.
4. Atur `AI_ENABLED=false` sampai provider, retensi, pemberitahuan privasi, dan dasar pemrosesan data untuk pemrosesan pesan oleh provider AI sudah disetujui. Jika diaktifkan, isi `AI_API_KEY`, `AI_MODEL`, dan URL HTTPS provider.
5. Siapkan volume milik UID 1000 (user container), bangun, dan jalankan:

   ```sh
   mkdir -p storage
   sudo chown -R 1000:1000 storage
   chmod 700 storage
   docker compose build
   docker compose up -d
   ```

Compose membatasi port host ke `127.0.0.1:3000`. Letakkan reverse proxy TLS di host, batasi akses dashboard, dan teruskan webhook HTTPS Meta ke layanan. Container berjalan non-root, filesystem root read-only, tanpa Linux capabilities, dan mempunyai batas CPU/memori.

Jika reverse proxy memakai alamat jaringan container, isi `TRUSTED_PROXY_CIDRS` hanya dengan CIDR proxy yang sudah diperiksa. Jika kosong, `X-Forwarded-For` tidak dipercaya dan pembatasan permintaan memakai alamat socket langsung.

Layanan menolak start production jika hash admin, secret gateway, kredensial Cloud API, versi Graph API, atau nilai OTP tidak valid. Dashboard dapat menyimpan perubahan runtime secara atomik di `storage/runtime.env` dengan izin `0600`. Database, antrean, dan basis pengetahuan berada di `storage`; atur backup terenkripsi dan retensinya sesuai kebijakan privasi.

## Biaya: gratis dan berbayar

Gateway ini berjalan di server yang Anda kelola sendiri dan tidak menagih biaya lisensi atau langganan aplikasi. Namun, gateway menghubungkan layanan eksternal yang dapat memiliki tarif sendiri. Biaya berikut tidak dibayar ke project ini.

| Komponen | Gratis atau berbayar | Keterangan |
| --- | --- | --- |
| Kode gateway, dashboard, dan SQLite | Tidak ada biaya aplikasi dari gateway | Anda tetap menanggung komputer/server, listrik, penyimpanan, dan koneksi internet yang digunakan untuk menjalankannya. |
| Mode `mock` | Gratis untuk uji alur lokal | Tidak mengirim pesan WhatsApp sungguhan; hanya untuk pengembangan. |
| Mode QR `whatsmeow` | Tidak ada tarif pesan Cloud API Meta | Ini integrasi WhatsApp Web tidak resmi untuk eksperimen development. Project menolak mode ini di production. Perangkat yang tertaut tetap memerlukan koneksi internet; sesi atau akun dapat terganggu. OTP di mode development dikirim sebagai teks biasa, bukan template OTP resmi Cloud API. |
| WhatsApp Business Cloud API | Ada biaya untuk kategori pesan tertentu | Meta mengenakan tarif per pesan yang berhasil dikirimkan, sesuai kategori dan negara penerima. Template autentikasi seperti OTP dan pesan marketing dapat dikenai tarif. Dalam jendela layanan pelanggan 24 jam setelah pesan pengguna, Meta saat ini menyatakan pesan layanan dan pesan utility sebagai respons kepada pengguna tidak dikenai biaya. Di luar jendela tersebut, pesan harus memakai template yang disetujui dan tarif kategori yang berlaku dapat dikenakan. Periksa rate card Meta terbaru karena tarif dan ketentuannya dapat berubah. |
| Provider AI melalui API | Bisa berbayar atau memiliki kuota gratis terbatas | Biaya dan batas penggunaan ditentukan provider, bukan gateway. API key yang dipakai dapat terhubung ke akun berbayar. Jangan menganggap API gratis kecuali syarat provider menyatakannya. |
| Model AI yang dijalankan lokal | Tidak ada tarif API per permintaan | Model tidak disertakan dalam project. Anda harus memasang dan menjalankan server model OpenAI-compatible sendiri; penggunaan tetap memakai RAM/VRAM, listrik, ruang disk, dan perangkat komputer. Gateway mensyaratkan kolom API key tidak kosong; server lokal yang tidak memakai autentikasi mungkin menerima nilai placeholder, sesuai konfigurasi server tersebut. |
| Hosting, domain, dan BSP pihak ketiga | Bergantung pilihan | Menjalankan sendiri di komputer menghindari biaya sewa VPS, tetapi akses publik HTTPS, domain, listrik, atau penyedia solusi WhatsApp dapat menambah biaya. BSP tidak diperlukan oleh integrasi Cloud API langsung di project ini. |

### Ringkasan skenario biaya

- **Uji coba tanpa tarif pesan Meta:** jalankan `APP_ENV=development` dengan `WHATSAPP_MODE=whatsmeow` dan, bila ingin balasan AI, provider model lokal. Ini bukan konfigurasi production resmi; OTP development berupa teks biasa dan integrasi WhatsApp Web tidak resmi.
- **Production dengan OTP resmi:** gunakan `WHATSAPP_MODE=cloud` dan template autentikasi Meta yang disetujui. Anggarkan tarif Meta untuk OTP yang terkirim, serta biaya hosting dan provider AI jika memakai AI API berbayar. Jendela balasan layanan gratis tidak menghapus tarif template OTP.
- **Production dengan AI lokal:** dapat menghindari tagihan API AI per permintaan, tetapi tetap memerlukan Cloud API untuk jalur production project ini. Biaya pesan Meta yang berlaku, terutama OTP, tetap terpisah dari biaya AI.

Harga WhatsApp berubah menurut kategori, pasar, dan kebijakan Meta. Lihat [halaman harga resmi WhatsApp Business Platform](https://whatsappbusiness.com/products/platform-pricing/) sebelum mengaktifkan pengiriman berbayar.

## Pemeriksaan layanan

- `GET /health` memeriksa liveness proses.
- `GET /ready` mengembalikan `200` hanya setelah Cloud API tervalidasi; selain itu `503`.
- `GET /status` memberi ringkasan status tanpa nomor, QR, pairing code, atau secret.
- Dashboard berada di `/` dan memerlukan kata sandi admin. Sesi memakai cookie `HttpOnly`, `Secure` di production, dan `SameSite=Strict`.

## API utama

- `POST /api/otp/send`: kirim OTP. Body: `{"phoneNumber":"081234567890","purpose":"default"}`. Tujuan yang diterima: `default` atau `reset`. Kode OTP tidak pernah dikembalikan oleh API. Pengiriman memakai template Cloud API yang disetujui.
- `POST /api/otp/verify`: verifikasi dengan body `{"phoneNumber":"081234567890","code":"123456","purpose":"default"}`. OTP terikat pada nomor dan tujuan, hanya sekali pakai, mempunyai batas percobaan, expiry, cooldown, dan disimpan sebagai hash di memori.
- `POST /send`: memerlukan header `X-Gateway-Secret`. Body: `{"target":"081234567890","message":"Halo","async":true}`. Respons `202` memberikan `job_id` dan URL status. Permintaan sinkron hanya sukses jika Graph API menerima pesan.
- `GET /send/{job_id}`: memerlukan `X-Gateway-Secret`; dashboard mempunyai `GET /api/dashboard/messages/{job_id}`.
- `GET /webhooks/whatsapp` dan `POST /webhooks/whatsapp`: verifikasi webhook Meta dan signature HMAC. Pesan masuk disimpan dahulu ke inbox SQLite, dideduplikasi, lalu isi pesan dan kontak dihapus setelah pemrosesan; ID deduplikasi disimpan hingga 72 jam.

Penerusan ke `WEBHOOK_URL` juga disimpan dahulu dalam antrean SQLite. Kegagalan sementara dicoba ulang maksimal lima kali; penerima mendapat `event_id` di payload dan `Idempotency-Key` pada header, sehingga perlu mendeduplikasi event yang sama. Pesan dan nomor dihapus dari antrean setelah diterima atau setelah retry habis. Pengiriman bersifat at-least-once jika proses mati sesudah penerima menerima event tetapi sebelum status tersimpan.

Status pekerjaan async `accepted` berarti Meta menerima permintaan API, bukan bukti bahwa perangkat penerima sudah menerima pesan. `delivery_status` tetap `queued` sampai webhook Meta memberi status `sent`, `delivered`, `read`, atau `failed`. Retry memakai kebijakan terbatas; jika proses berhenti sesudah Meta menerima permintaan tetapi sebelum status SQLite tersimpan, retry dapat menyebabkan pesan ganda karena API pengiriman tidak menyediakan kunci idempotensi.

## Pengembangan dan verifikasi

Gunakan Go 1.27 atau versi yang didukung `go.mod`:

```sh
go test ./...
go vet ./...
go build ./cmd/wa-gateway
```

Mode `mock` dan `whatsmeow` hanya untuk pengembangan (`APP_ENV=development`). Layanan tidak membuat respons sukses palsu ketika pesan belum terkirim. Jalankan pemeriksaan security/policy, beban, backup-restore, dan uji pemulihan webhook di staging dengan nomor serta template sandbox sebelum melayani trafik nyata.

## Batas kesiapan release

Kode ini menyiapkan kontrol aplikasi dan deployment, tetapi release operasional memerlukan kredensial Meta, template OTP yang disetujui, endpoint publik HTTPS, secret admin/gateway, kebijakan opt-in/opt-out dan eskalasi manusia, serta kajian privasi Indonesia untuk nomor, isi chat, webhook, dan provider AI. Dashboard dan proses tidak mengaktifkan AI secara default pada konfigurasi contoh.
