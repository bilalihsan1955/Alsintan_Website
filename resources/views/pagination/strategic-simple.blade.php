@if ($paginator->hasPages())
    <nav class="inline-flex items-center gap-0.5 text-xs text-slate-700" role="navigation" aria-label="Pagination">
        @if ($paginator->onFirstPage())
            <span class="inline-flex h-8 min-w-[2rem] items-center justify-center rounded-md border border-slate-100 text-slate-300" aria-disabled="true">‹</span>
        @else
            <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="inline-flex h-8 min-w-[2rem] items-center justify-center rounded-md border border-slate-200 bg-white hover:bg-slate-50">‹</a>
        @endif
        <span class="inline-flex h-8 min-w-[3.25rem] items-center justify-center px-1 tabular-nums text-slate-600">{{ $paginator->currentPage() }}/{{ $paginator->lastPage() }}</span>
        @if ($paginator->hasMorePages())
            <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="inline-flex h-8 min-w-[2rem] items-center justify-center rounded-md border border-slate-200 bg-white hover:bg-slate-50">›</a>
        @else
            <span class="inline-flex h-8 min-w-[2rem] items-center justify-center rounded-md border border-slate-100 text-slate-300" aria-disabled="true">›</span>
        @endif
    </nav>
@endif
