@php
    $statusLabels = ['pending' => 'Belum di-scan', 'scanning' => 'Sedang scan', 'completed' => 'Selesai', 'failed' => 'Gagal'];
@endphp
<span class="badge badge-{{ $status }}">{{ $statusLabels[$status] ?? ucfirst($status) }}</span>
