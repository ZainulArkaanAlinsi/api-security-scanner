@extends('layouts.auth')

@section('title', 'Daftar')

@section('content')
<h1>Buat akun</h1>
<p class="lede">Mulai scan API pertamamu dalam kurang dari satu menit.</p>

<form action="{{ route('register') }}" method="POST" novalidate>
    @csrf

    <div class="field {{ $errors->has('name') ? 'has-error' : '' }}">
        <label class="label" for="name">Nama</label>
        <input class="input" type="text" id="name" name="name" value="{{ old('name') }}" required autofocus autocomplete="name">
        @error('name')<p class="error">{{ $message }}</p>@enderror
    </div>

    <div class="field {{ $errors->has('email') ? 'has-error' : '' }}">
        <label class="label" for="email">Email</label>
        <input class="input" type="email" id="email" name="email" value="{{ old('email') }}"
            placeholder="nama@perusahaan.com" required autocomplete="username"
            @error('email') aria-invalid="true" aria-describedby="email-error" @enderror>
        @error('email')<p class="error" id="email-error">{{ $message }}</p>@enderror
    </div>

    <div class="field {{ $errors->has('password') ? 'has-error' : '' }}">
        <label class="label" for="password">Password</label>
        <div class="input-box">
            <input class="input" type="password" id="password" name="password" required autocomplete="new-password"
                aria-describedby="password-hint @error('password') password-error @enderror"
                @error('password') aria-invalid="true" @enderror>
            <button type="button" class="toggle-pass" data-toggle-pass="password" aria-pressed="false">Lihat</button>
        </div>
        @error('password')<p class="error" id="password-error">{{ $message }}</p>@enderror
        <p class="hint" id="password-hint">Minimal 8 karakter.</p>
    </div>

    <div class="field">
        <label class="label" for="password_confirmation">Ulangi password</label>
        <input class="input" type="password" id="password_confirmation" name="password_confirmation" required autocomplete="new-password">
    </div>

    <button type="submit" class="btn btn-primary btn-block" style="height:44px;margin-top:1.5rem">Buat akun</button>
</form>

<p class="switch">Sudah punya akun? <a class="link" href="{{ route('login') }}">Masuk</a></p>
@endsection
