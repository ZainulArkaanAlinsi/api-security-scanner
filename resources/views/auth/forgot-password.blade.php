@extends('layouts.auth')

@section('title', 'Lupa password')

@section('content')
<h1>Lupa password</h1>
<p class="lede">Masukkan email akunmu. Kami kirim link untuk membuat password baru.</p>

@if (session('status'))
    <div class="alert alert-success" role="status">{{ session('status') }}</div>
@endif

<form action="{{ route('password.email') }}" method="POST" novalidate>
    @csrf

    <div class="field {{ $errors->has('email') ? 'has-error' : '' }}">
        <label class="label" for="email">Email</label>
        <input class="input" type="email" id="email" name="email" value="{{ old('email') }}"
            placeholder="nama@perusahaan.com" required autofocus autocomplete="username">
        @error('email')<p class="error">{{ $message }}</p>@enderror
    </div>

    <button type="submit" class="btn btn-primary btn-block" style="height:44px;margin-top:1.5rem">Kirim link reset</button>
</form>

<p class="switch">Ingat password? <a class="link" href="{{ route('login') }}">Kembali ke halaman masuk</a></p>
@endsection
