@if ($paginator->hasPages())
<nav class="pager" aria-label="Navigasi halaman">
    <span class="muted">
        {{ $paginator->firstItem() }}–{{ $paginator->lastItem() }} dari {{ $paginator->total() }}
    </span>
    <div class="row">
        @if ($paginator->onFirstPage())
            <span class="btn btn-secondary btn-sm" aria-disabled="true">Sebelumnya</span>
        @else
            <a class="btn btn-secondary btn-sm" href="{{ $paginator->previousPageUrl() }}" rel="prev">Sebelumnya</a>
        @endif

        <span class="faint mono">{{ $paginator->currentPage() }} / {{ $paginator->lastPage() }}</span>

        @if ($paginator->hasMorePages())
            <a class="btn btn-secondary btn-sm" href="{{ $paginator->nextPageUrl() }}" rel="next">Berikutnya</a>
        @else
            <span class="btn btn-secondary btn-sm" aria-disabled="true">Berikutnya</span>
        @endif
    </div>
</nav>
@endif
