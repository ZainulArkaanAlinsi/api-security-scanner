@extends('errors.layout')

@section('code', '419')
@section('title', 'Sesi sudah berakhir')
@section('message', 'Halaman terlalu lama dibiarkan terbuka sehingga formulir kedaluwarsa. Muat ulang halaman lalu kirim lagi.')

@section('actions')
    <button type="button" class="btn btn-primary" onclick="history.back()">Kembali ke formulir</button>
    <a href="{{ url('/') }}" class="btn btn-ghost">Ke halaman utama</a>
@endsection
