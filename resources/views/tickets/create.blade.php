@extends('layouts.app')

@section('title', 'Scan baru')

@section('content')
<div style="max-width:640px">
    <a href="{{ route('tickets.index') }}" class="back">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 18l-6-6 6-6"/></svg>
        Dashboard
    </a>

    <div class="page-head">
        <div>
            <h1>Scan baru</h1>
            <p>Simpan endpoint yang ingin dicek. Kamu bisa menjalankan scan setelah ticket dibuat.</p>
        </div>
    </div>

    <form action="{{ route('tickets.store') }}" method="POST" class="card card-pad" novalidate>
        @csrf
        @include('tickets.partials.form')

        <div class="row" style="margin-top:1.5rem">
            <button type="submit" class="btn btn-primary">Buat ticket</button>
            <a href="{{ route('tickets.index') }}" class="btn btn-ghost">Batal</a>
        </div>
    </form>
</div>
@endsection
