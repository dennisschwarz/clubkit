@if ($paginator->hasPages())
<nav class="ck-pagination" aria-label="Seitennavigation">

    {{-- Vorherige Seite --}}
    @if ($paginator->onFirstPage())
        <span class="ck-pagination__btn ck-pagination__btn--disabled" aria-disabled="true">&#8249;</span>
    @else
        <a class="ck-pagination__btn" href="{{ $paginator->previousPageUrl() }}" rel="prev" aria-label="Vorherige Seite">&#8249;</a>
    @endif

    {{-- Seitenzahlen --}}
    @foreach ($elements as $element)
        {{-- Auslassungspunkte --}}
        @if (is_string($element))
            <span class="ck-pagination__dots">{{ $element }}</span>
        @endif

        {{-- Seitenlinks --}}
        @if (is_array($element))
            @foreach ($element as $page => $url)
                @if ($page == $paginator->currentPage())
                    <span class="ck-pagination__btn ck-pagination__btn--active" aria-current="page">{{ $page }}</span>
                @else
                    <a class="ck-pagination__btn" href="{{ $url }}">{{ $page }}</a>
                @endif
            @endforeach
        @endif
    @endforeach

    {{-- Nächste Seite --}}
    @if ($paginator->hasMorePages())
        <a class="ck-pagination__btn" href="{{ $paginator->nextPageUrl() }}" rel="next" aria-label="Nächste Seite">&#8250;</a>
    @else
        <span class="ck-pagination__btn ck-pagination__btn--disabled" aria-disabled="true">&#8250;</span>
    @endif

</nav>
@endif
