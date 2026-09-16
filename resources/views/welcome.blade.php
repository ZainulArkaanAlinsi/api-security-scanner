<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>API Scanner - Cek keamanan API kamu</title>
    <meta name="description" content="Scan endpoint API untuk menemukan header keamanan yang hilang, CORS terbuka, cookie tidak aman, dan kebocoran pesan debug.">

    @include('partials.theme')

    <style>
        .container { max-width: 1120px; margin: 0 auto; padding: 0 clamp(1rem, 4vw, 2rem); }

        .top { display: flex; align-items: center; justify-content: space-between; height: 64px; }
        .top .row { gap: 0.4rem; }

        .hero {
            display: grid;
            grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
            gap: clamp(2rem, 5vw, 4rem);
            align-items: center;
            padding-top: clamp(3rem, 8vw, 6rem);
            padding-bottom: clamp(3rem, 7vw, 5rem);
        }

        .hero h1 {
            font-size: clamp(2rem, 4.2vw, 3rem);
            letter-spacing: -0.035em;
            line-height: 1.08;
            max-width: 14ch;
        }

        .hero-lede { margin-top: 1.1rem; font-size: 1.05rem; color: var(--ink-soft); max-width: 46ch; }
        .hero .row { margin-top: 1.75rem; }
        .hero .btn { height: 44px; padding: 0 1.2rem; font-size: 0.925rem; }
        .hero-note { margin-top: 0.9rem; font-size: 0.8rem; color: var(--ink-faint); }

        .band { border-top: 1px solid var(--line); padding: clamp(3rem, 6vw, 4.5rem) 0; }
        .band-head { display: grid; grid-template-columns: minmax(0, 1fr) minmax(0, 1.4fr); gap: 2rem; margin-bottom: 2.25rem; }
        .band-head h2 { font-size: 1.5rem; letter-spacing: -0.025em; line-height: 1.2; }
        .band-head p { color: var(--ink-soft); }

        .check-list {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            border-top: 1px solid var(--line);
            border-left: 1px solid var(--line);
        }
        .check-item { padding: 1.1rem 1.25rem 1.25rem; border-right: 1px solid var(--line); border-bottom: 1px solid var(--line); }
        .check-item .row { justify-content: space-between; margin-bottom: 0.4rem; }
        .check-item h3 { font-size: 0.925rem; font-weight: 500; }
        .check-item p { font-size: 0.85rem; color: var(--ink-soft); }

        .steps { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 2rem; list-style: none; counter-reset: step; }
        .steps li { counter-increment: step; }
        .steps li::before {
            content: '0' counter(step);
            display: block;
            margin-bottom: 0.75rem;
            padding-bottom: 0.75rem;
            border-bottom: 1px solid var(--line);
            font-family: var(--mono);
            font-size: 0.8rem;
            color: var(--ink-faint);
        }
        .steps h3 { font-size: 1rem; font-weight: 500; margin-bottom: 0.3rem; }
        .steps p { font-size: 0.9rem; color: var(--ink-soft); }

        .cta { display: flex; justify-content: space-between; align-items: center; gap: 1.5rem; flex-wrap: wrap; }
        .cta h2 { font-size: 1.5rem; letter-spacing: -0.025em; }

        .footer { border-top: 1px solid var(--line); padding: 1.25rem 0; font-size: 0.8rem; color: var(--ink-faint); }

        @media (max-width: 880px) {
            .hero, .band-head { grid-template-columns: 1fr; }
            .check-list { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .steps { grid-template-columns: 1fr; gap: 1.5rem; }
        }
        @media (max-width: 560px) {
            .check-list { grid-template-columns: 1fr; }
        }
    </style>
</head>

<body>
    <header class="container top">
        <a href="{{ route('home') }}" class="brand">
            <span class="brand-mark" aria-hidden="true"></span>
            API Scanner
        </a>
        <nav class="row" aria-label="Akun">
            <a href="{{ route('login') }}" class="btn btn-ghost">Masuk</a>
            <a href="{{ route('register') }}" class="btn btn-primary">Daftar</a>
        </nav>
    </header>

    <main>
        <section class="container hero">
            <div>
                @if (session('success'))
                    <div class="alert alert-success" role="status" style="margin-bottom:1.5rem">{{ session('success') }}</div>
                @endif
                <p class="eyebrow">Audit keamanan API</p>
                <h1 style="margin-top:0.9rem">Temukan celah di API kamu sebelum orang lain.</h1>
                <p class="hero-lede">Masukkan URL endpoint, jalankan scan, dan dapatkan daftar masalah konfigurasi yang diurutkan dari yang paling berisiko, lengkap dengan cara memperbaikinya.</p>
                <div class="row">
                    <a href="{{ route('register') }}" class="btn btn-primary">Mulai scan gratis</a>
                    <a href="#pemeriksaan" class="btn btn-secondary">Lihat yang dicek</a>
                </div>
                <p class="hero-note">Tanpa instalasi. Riwayat scan tersimpan, laporan bisa dicetak atau diunduh.</p>
            </div>
            @include('partials.scan-preview')
        </section>

        <section class="band" id="pemeriksaan">
            <div class="container">
                <div class="band-head">
                    <h2>Hingga 12 pemeriksaan di setiap scan</h2>
                    <p>Fokus pada kesalahan konfigurasi yang paling sering ditemukan di API production: hal kecil yang gampang terlewat, tapi sering jadi pintu masuk serangan.</p>
                </div>

                <div class="check-list">
                    @foreach ([
                        ['HTTPS', 'high', 'Memastikan data tidak dikirim tanpa enkripsi.'],
                        ['Stack trace & debug', 'high', 'Mendeteksi pesan error internal yang bocor ke respons.'],
                        ['CORS', 'high', 'Menandai origin wildcard, apalagi jika digabung credentials.'],
                        ['Masa berlaku sertifikat', 'medium', 'Peringatan sebelum sertifikat TLS kedaluwarsa.'],
                        ['HSTS', 'medium', 'Mencegah browser diarahkan kembali ke http.'],
                        ['Cookie aman', 'medium', 'Mengecek flag HttpOnly dan Secure di setiap cookie.'],
                        ['Error server 5xx', 'medium', 'Endpoint yang crash sering menandakan input tak tervalidasi.'],
                        ['X-Content-Type-Options', 'low', 'Mencegah browser menebak tipe konten.'],
                        ['Clickjacking', 'low', 'X-Frame-Options atau CSP frame-ancestors.'],
                        ['Content-Security-Policy', 'low', 'Membatasi sumber script dan konten.'],
                        ['Kebocoran versi', 'low', 'Header Server atau X-Powered-By yang menyebut versi.'],
                        ['Waktu respons', 'low', 'Endpoint lambat lebih mudah dibuat down.'],
                    ] as [$name, $severity, $desc])
                        <div class="check-item">
                            <div class="row">
                                <h3>{{ $name }}</h3>
                                <span class="sev sev-{{ $severity }}">{{ $severity }}</span>
                            </div>
                            <p>{{ $desc }}</p>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>

        <section class="band">
            <div class="container">
                <div class="band-head">
                    <h2>Cara kerjanya</h2>
                    <p>Tiga langkah, tanpa konfigurasi apa pun di server kamu.</p>
                </div>
                <ol class="steps">
                    <li>
                        <h3>Tambahkan endpoint</h3>
                        <p>Beri judul dan tempel URL API publik yang ingin kamu periksa.</p>
                    </li>
                    <li>
                        <h3>Jalankan scan</h3>
                        <p>Scanner mengirim request dan memeriksa respons, header, serta cookie dalam beberapa detik.</p>
                    </li>
                    <li>
                        <h3>Perbaiki & scan ulang</h3>
                        <p>Ikuti saran perbaikan tiap temuan, lalu scan ulang untuk memastikan semuanya lolos.</p>
                    </li>
                </ol>
            </div>
        </section>

        <section class="band">
            <div class="container cta">
                <h2>Siap mengecek API pertamamu?</h2>
                <div class="row">
                    <a href="{{ route('register') }}" class="btn btn-primary">Buat akun</a>
                    <a href="{{ route('login') }}" class="btn btn-ghost">Sudah punya akun</a>
                </div>
            </div>
        </section>
    </main>

    <footer class="footer">
        <div class="container">&copy; {{ date('Y') }} API Scanner</div>
    </footer>
</body>

</html>
