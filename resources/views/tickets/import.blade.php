@extends('layouts.app')

@section('title', 'Import OpenAPI')

@section('content')
<div style="max-width:640px">
    <a href="{{ route('tickets.index') }}" class="back">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 18l-6-6 6-6"/></svg>
        Dashboard
    </a>

    <div class="page-head">
        <div>
            <h1>Import dari OpenAPI</h1>
            <p>Tempel URL dokumen OpenAPI 3 atau Swagger 2, dan semua endpoint GET langsung jadi ticket.</p>
        </div>
    </div>

    <form action="{{ route('tickets.import') }}" method="POST" class="card card-pad" novalidate data-busy="Mengambil dokumen…">
        @csrf

        <div class="field {{ $errors->has('spec_url') ? 'has-error' : '' }}">
            <label class="label" for="spec_url">URL dokumen</label>
            <input class="input mono" style="font-size:0.875rem" type="url" id="spec_url" name="spec_url"
                value="{{ old('spec_url') }}" placeholder="https://api.domainkamu.com/openapi.json" required autofocus
                aria-describedby="spec_url-hint @error('spec_url') spec_url-error @enderror"
                @error('spec_url') aria-invalid="true" @enderror>
            @error('spec_url')<p class="error" id="spec_url-error">{{ $message }}</p>@enderror
            <p class="hint" id="spec_url-hint">Format JSON atau YAML. Maksimal {{ config('scanner.import_limit', 50) }} endpoint sekali impor.</p>
        </div>

        <div class="field">
            <label class="check">
                <input type="checkbox" name="scan_now" value="1" {{ old('scan_now', true) ? 'checked' : '' }}>
                Langsung scan semua endpoint setelah diimpor
            </label>
        </div>

        <div class="row" style="margin-top:1.5rem">
            <button type="submit" class="btn btn-primary">Import endpoint</button>
            <a href="{{ route('tickets.create') }}" class="btn btn-ghost">Tambah satu per satu</a>
        </div>
    </form>

    <div class="card card-pad" style="margin-top:1.25rem">
        <h2>Yang perlu diketahui</h2>
        <ul class="muted" style="margin-top:0.6rem;padding-left:1.1rem;font-size:0.875rem;line-height:1.8">
            <li>Hanya operasi <b>GET</b> yang diambil, karena scanner tidak pernah mengirim data.</li>
            <li>Parameter di path seperti <span class="mono">/users/{id}</span> diisi angka <span class="mono">1</span>.</li>
            <li>Endpoint yang URL-nya sudah ada di daftarmu akan dilewati.</li>
            <li>Dokumen di alamat internal (localhost, 192.168.x.x) ditolak, sama seperti aturan scan.</li>
        </ul>
    </div>
</div>
@endsection
