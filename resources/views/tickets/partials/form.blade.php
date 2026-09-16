<div class="field {{ $errors->has('title') ? 'has-error' : '' }}">
    <label class="label" for="title">Judul</label>
    <input class="input" type="text" id="title" name="title" value="{{ old('title', $ticket->title ?? '') }}"
        placeholder="Contoh: API pembayaran production" required maxlength="255" autofocus
        @error('title') aria-invalid="true" aria-describedby="title-error" @enderror>
    @error('title')<p class="error" id="title-error">{{ $message }}</p>@enderror
</div>

<div class="field {{ $errors->has('api_url') ? 'has-error' : '' }}">
    <label class="label" for="api_url">URL endpoint</label>
    <input class="input mono" style="font-size:0.875rem" type="url" id="api_url" name="api_url"
        value="{{ old('api_url', $ticket->api_url ?? '') }}" placeholder="https://api.domainkamu.com/v1/health" required
        aria-describedby="api_url-hint @error('api_url') api_url-error @enderror"
        @error('api_url') aria-invalid="true" @enderror>
    @error('api_url')
        <p class="error" id="api_url-error">{{ $message }}</p>
    @enderror
    <p class="hint" id="api_url-hint">Harus URL publik pada port 80 atau 443. Alamat internal seperti localhost atau 192.168.x.x akan ditolak saat scan.</p>
</div>

<div class="field {{ $errors->has('description') ? 'has-error' : '' }}">
    <label class="label" for="description">Catatan <span class="faint" style="font-weight:400">opsional</span></label>
    <textarea class="input" id="description" name="description" rows="3" maxlength="2000"
        placeholder="Misalnya: endpoint ini dipakai aplikasi mobile versi 3.x">{{ old('description', $ticket->description ?? '') }}</textarea>
    @error('description')<p class="error">{{ $message }}</p>@enderror
</div>
