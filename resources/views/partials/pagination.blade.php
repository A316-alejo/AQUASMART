@if ($paginator->hasPages())
    <nav class="pagination" aria-label="Paginación">
        <span>
            @if (method_exists($paginator, 'total'))
                Mostrando {{ $paginator->firstItem() }}–{{ $paginator->lastItem() }} de {{ $paginator->total() }}
            @else
                Página {{ $paginator->currentPage() }}
            @endif
        </span>

        <div class="pages">
            @if ($paginator->onFirstPage())
                <span class="disabled" aria-disabled="true">Anterior</span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" rel="prev">Anterior</a>
            @endif

            @isset($elements)
                @foreach ($elements as $element)
                    @if (is_string($element))
                        <span class="disabled" aria-disabled="true">{{ $element }}</span>
                    @else
                        @foreach ($element as $page => $url)
                            @if ($page == $paginator->currentPage())
                                <span class="current" aria-current="page">{{ $page }}</span>
                            @else
                                <a href="{{ $url }}" aria-label="Ir a la página {{ $page }}">{{ $page }}</a>
                            @endif
                        @endforeach
                    @endif
                @endforeach
            @endisset

            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" rel="next">Siguiente</a>
            @else
                <span class="disabled" aria-disabled="true">Siguiente</span>
            @endif
        </div>
    </nav>
@endif
