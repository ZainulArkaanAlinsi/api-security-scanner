@extends('errors.layout')

@section('code', '419')
@section('title', 'Sesi sudah berakhir')
@section('message', 'Halaman terlalu lama dibiarkan terbuka sehingga formulir kedaluwarsa. Muat ulang halaman lalu kirim lagi.')

@section('actions')
    <a href="javascript:history.back()" class="btn btn-primary">Kembali ke formulir</a>
    <a href="{{ url('/') }}" class="btn btn-ghost">Ke halaman utama</a>
@endsection
