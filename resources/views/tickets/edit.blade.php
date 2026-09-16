@extends('layouts.app')

@section('title', 'Edit ticket')

@section('content')
<div style="max-width:640px">
    <a href="{{ route('tickets.show', $ticket) }}" class="back">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 18l-6-6 6-6"/></svg>
        Kembali ke ticket
    </a>

    <div class="page-head">
        <div>
            <h1>Edit ticket</h1>
            <p class="truncate">{{ $ticket->title }}</p>
        </div>
    </div>

    @if ($ticket->scanned_at)
        <div class="alert alert-info" style="margin-bottom:1.25rem">
            Mengganti URL akan menghapus hasil scan sebelumnya, karena hasil itu milik endpoint lama.
        </div>
    @endif

    <form action="{{ route('tickets.update', $ticket) }}" method="POST" class="card card-pad" novalidate>
        @csrf
        @method('PUT')
        @include('tickets.partials.form')

        <div class="row" style="margin-top:1.5rem">
            <button type="submit" class="btn btn-primary">Simpan perubahan</button>
            <a href="{{ route('tickets.show', $ticket) }}" class="btn btn-ghost">Batal</a>
        </div>
    </form>
</div>
@endsection
