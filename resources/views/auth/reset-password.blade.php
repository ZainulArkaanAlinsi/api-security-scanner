@extends('layouts.auth')

@section('title', 'Buat password baru')

@section('content')
<h1>Buat password baru</h1>
<p class="lede">Pilih password yang belum pernah kamu pakai di akun ini.</p>

@if ($errors->has('email'))
    <div class="alert alert-error" role="alert">{{ $errors->first('email') }}</div>
@endif

<form action="{{ route('password.store') }}" method="POST" novalidate>
    @csrf
    <input type="hidden" name="token" value="{{ $token }}">

    <div class="field">
        <label class="label" for="email">Email</label>
        <input class="input" type="email" id="email" name="email" value="{{ old('email', $email) }}" required autocomplete="username">
    </div>

    <div class="field {{ $errors->has('password') ? 'has-error' : '' }}">
        <label class="label" for="password">Password baru</label>
        <div class="input-box">
            <input class="input" type="password" id="password" name="password" required autofocus autocomplete="new-password">
            <button type="button" class="toggle-pass" data-toggle-pass="password" aria-pressed="false">Lihat</button>
        </div>
        @error('password')
            <p class="error">{{ $message }}</p>
        @else
            <p class="hint">Minimal 8 karakter.</p>
        @enderror
    </div>

    <div class="field">
        <label class="label" for="password_confirmation">Ulangi password baru</label>
        <input class="input" type="password" id="password_confirmation" name="password_confirmation" required autocomplete="new-password">
    </div>

    <button type="submit" class="btn btn-primary btn-block" style="height:44px;margin-top:1.5rem">Simpan password</button>
</form>
@endsection
