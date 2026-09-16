@extends('layouts.app')

@section('title', 'Profil & keamanan')

@push('styles')
<style>
    .settings { max-width: 760px; }
    .section {
        display: grid;
        grid-template-columns: 220px minmax(0, 1fr);
        gap: 1.5rem;
        padding: 1.75rem 0;
        border-top: 1px solid var(--line);
    }
    .section-intro p { margin-top: 0.3rem; font-size: 0.85rem; color: var(--ink-soft); }
    .account-stats { display: flex; gap: 2rem; }
    .account-stats b { display: block; font-size: 1.35rem; font-weight: 600; font-variant-numeric: tabular-nums; }
    .account-stats span { font-size: 0.8rem; color: var(--ink-soft); }
    .danger-card { border-color: color-mix(in srgb, var(--danger) 30%, var(--line)); }
    @media (max-width: 700px) {
        .section { grid-template-columns: 1fr; gap: 0.9rem; }
    }
</style>
@endpush

@section('content')
<div class="settings">
    <div class="page-head">
        <div>
            <h1>Profil & keamanan</h1>
            <p>Kelola data akun, password, dan akses kamu.</p>
        </div>
    </div>

    <section class="section">
        <div class="section-intro">
            <h2>Ringkasan akun</h2>
            <p>Terdaftar sejak {{ $user->created_at->format('d M Y') }}.</p>
        </div>
        <div class="card card-pad account-stats">
            <div><b>{{ $ticketCount }}</b><span>Ticket</span></div>
            <div><b>{{ $scanCount }}</b><span>Sudah di-scan</span></div>
        </div>
    </section>

    <section class="section">
        <div class="section-intro">
            <h2>Informasi profil</h2>
            <p>Nama tampil di menu, email dipakai untuk masuk dan reset password.</p>
        </div>
        <form action="{{ route('profile.update') }}" method="POST" class="card card-pad" novalidate>
            @csrf
            @method('PATCH')

            <div class="field {{ $errors->has('name') ? 'has-error' : '' }}">
                <label class="label" for="name">Nama</label>
                <input class="input" type="text" id="name" name="name" value="{{ old('name', $user->name) }}" required autocomplete="name">
                @error('name')<p class="error">{{ $message }}</p>@enderror
            </div>

            <div class="field {{ $errors->has('email') ? 'has-error' : '' }}">
                <label class="label" for="email">Email</label>
                <input class="input" type="email" id="email" name="email" value="{{ old('email', $user->email) }}" required autocomplete="username">
                @error('email')<p class="error">{{ $message }}</p>@enderror
            </div>

            <button type="submit" class="btn btn-primary" style="margin-top:1.25rem">Simpan profil</button>
        </form>
    </section>

    <section class="section">
        <div class="section-intro">
            <h2>Ganti password</h2>
            <p>Pakai password panjang yang tidak kamu gunakan di situs lain.</p>
        </div>
        <form action="{{ route('profile.password') }}" method="POST" class="card card-pad" novalidate>
            @csrf
            @method('PUT')

            <div class="field {{ $errors->password->has('current_password') ? 'has-error' : '' }}">
                <label class="label" for="current_password">Password saat ini</label>
                <input class="input" type="password" id="current_password" name="current_password" required autocomplete="current-password">
                @error('current_password', 'password')<p class="error">{{ $message }}</p>@enderror
            </div>

            <div class="field {{ $errors->password->has('password') ? 'has-error' : '' }}">
                <label class="label" for="new_password">Password baru</label>
                <input class="input" type="password" id="new_password" name="password" required autocomplete="new-password">
                @error('password', 'password')
                    <p class="error">{{ $message }}</p>
                @else
                    <p class="hint">Minimal 8 karakter.</p>
                @enderror
            </div>

            <div class="field">
                <label class="label" for="new_password_confirmation">Ulangi password baru</label>
                <input class="input" type="password" id="new_password_confirmation" name="password_confirmation" required autocomplete="new-password">
            </div>

            <button type="submit" class="btn btn-primary" style="margin-top:1.25rem">Ganti password</button>
        </form>
    </section>

    <section class="section">
        <div class="section-intro">
            <h2>API token</h2>
            <p>Untuk menjalankan scan dari pipeline CI/CD.</p>
        </div>
        <div>
            @if (session('new_api_token'))
                <div class="alert alert-success" style="display:block;margin-bottom:1rem">
                    <p style="font-weight:500">Token dibuat. Salin sekarang — tidak akan ditampilkan lagi.</p>
                    <input class="input mono" style="margin-top:0.6rem;font-size:0.8rem" type="text" readonly
                        value="{{ session('new_api_token') }}" onclick="this.select()" aria-label="Token baru">
                </div>
            @endif

            <form action="{{ route('api-tokens.store') }}" method="POST" class="card card-pad">
                @csrf
                <div class="field {{ $errors->token->has('name') ? 'has-error' : '' }}">
                    <label class="label" for="token_name">Nama token</label>
                    <input class="input" type="text" id="token_name" name="name" placeholder="GitHub Actions" required maxlength="60">
                    @error('name', 'token')<p class="error">{{ $message }}</p>@enderror
                </div>
                <button type="submit" class="btn btn-primary" style="margin-top:1rem">Buat token</button>
            </form>

            @if ($apiTokens->isNotEmpty())
                <div class="card" style="margin-top:1rem">
                    @foreach ($apiTokens as $token)
                        <div class="row" style="justify-content:space-between;padding:0.8rem 1.25rem;border-bottom:1px solid var(--line)">
                            <div>
                                <div style="font-size:0.9rem">{{ $token->name }}</div>
                                <div class="faint mono" style="font-size:0.75rem">
                                    {{ $token->preview() }} · {{ $token->last_used_at ? 'dipakai '.$token->last_used_at->diffForHumans() : 'belum pernah dipakai' }}
                                </div>
                            </div>
                            <form action="{{ route('api-tokens.destroy', $token) }}" method="POST"
                                data-confirm="Hapus token &quot;{{ $token->name }}&quot;? Pipeline yang memakainya akan langsung ditolak.">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-ghost btn-sm">Hapus</button>
                            </form>
                        </div>
                    @endforeach
                </div>
            @endif

            <details style="margin-top:1rem">
                <summary style="cursor:pointer;font-size:0.875rem;color:var(--ink-soft)">Contoh pemakaian di GitHub Actions</summary>
                <pre class="card card-pad mono" style="margin-top:0.6rem;font-size:0.75rem;overflow-x:auto">curl -X POST {{ url('/api/v1/scans') }} \
  -H "Authorization: Bearer $API_SCANNER_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"url":"https://api.domainkamu.com/v1/health"}'</pre>
            </details>
        </div>
    </section>

    <section class="section">
        <div class="section-intro">
            <h2>Hapus akun</h2>
            <p>Akun, semua ticket, dan hasil scan akan dihapus permanen.</p>
        </div>
        <form action="{{ route('profile.destroy') }}" method="POST" class="card card-pad danger-card" novalidate
            data-confirm="Hapus akun beserta semua ticket? Tindakan ini tidak bisa dibatalkan.">
            @csrf
            @method('DELETE')

            <div class="field {{ $errors->deleteAccount->has('password') ? 'has-error' : '' }}">
                <label class="label" for="delete_password">Masukkan password untuk konfirmasi</label>
                <input class="input" type="password" id="delete_password" name="password" required autocomplete="current-password">
                @error('password', 'deleteAccount')<p class="error">{{ $message }}</p>@enderror
            </div>

            <button type="submit" class="btn btn-danger" style="margin-top:1.25rem">Hapus akun permanen</button>
        </form>
    </section>
</div>
@endsection
