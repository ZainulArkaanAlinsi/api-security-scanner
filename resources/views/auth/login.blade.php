@extends('layouts.auth')

@section('title', 'Masuk')

@section('content')
<h1>Masuk ke akun</h1>
<p class="lede">Lanjutkan audit keamanan API yang sedang berjalan.</p>

@if (session('status'))
    <div class="alert alert-success" role="status">{{ session('status') }}</div>
@elseif (session('success'))
    <div class="alert alert-success" role="status">{{ session('success') }}</div>
@endif

@if ($errors->any())
    <div class="alert alert-error" role="alert">{{ $errors->first() }}</div>
@endif

<form action="{{ route('login') }}" method="POST" novalidate>
    @csrf

    <div class="field {{ $errors->has('email') ? 'has-error' : '' }}">
        <label class="label" for="email">Email</label>
        <input class="input" type="email" id="email" name="email" value="{{ old('email') }}"
            placeholder="nama@perusahaan.com" required autofocus autocomplete="username">
    </div>

    <div class="field {{ $errors->has('password') ? 'has-error' : '' }}">
        <label class="label" for="password">
            Password
            <a class="link" href="{{ route('password.request') }}">Lupa password?</a>
        </label>
        <div class="input-box">
            <input class="input" type="password" id="password" name="password" required autocomplete="current-password">
            <button type="button" class="toggle-pass" data-toggle-pass="password" aria-pressed="false">Lihat</button>
        </div>
    </div>

    <div class="field">
        <label class="check">
            <input type="checkbox" name="remember" {{ old('remember') ? 'checked' : '' }}>
            Tetap masuk di perangkat ini
        </label>
    </div>

    <button type="submit" class="btn btn-primary btn-block" style="height:44px;margin-top:1.5rem">Masuk</button>
</form>

<p class="switch">Belum punya akun? <a class="link" href="{{ route('register') }}">Daftar gratis</a></p>
@endsection
