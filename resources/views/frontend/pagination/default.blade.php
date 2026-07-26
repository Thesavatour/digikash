@if ($paginator->hasPages())
    @php($current = $paginator->currentPage())

    <div class="pagination-area text-center">
        <ul class="pagination-list">
            {{-- Previous Page Link --}}
            @if ($paginator->onFirstPage())
                <li>
                    <a class="prev disabled pagination-btn" href="#" style="pointer-events: none;">
                        <span class="d-none d-sm-inline">{{ __('Previous') }}</span>
                        <i class="fa-solid fa-chevron-left d-inline d-sm-none"></i>
                    </a>
                </li>
            @else
                <li>
                    <a class="prev pagination-btn active" href="{{ $paginator->previousPageUrl() }}" rel="prev">
                        <span class="d-none d-sm-inline">{{ __('Previous') }}</span>
                        <i class="fa-solid fa-chevron-left d-inline d-sm-none"></i>
                    </a>
                </li>
            @endif

            {{-- Pagination Elements --}}
            @foreach ($elements as $element)
                {{-- "Three Dots" Separator --}}
                @if (is_string($element))
                    <li>
                        <a class="pagination-btn disabled" href="#" style="pointer-events: none;">{{ $element }}</a>
                    </li>
                @endif

                {{-- Array Of Links --}}
                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        @if ($page == $current)
                            <li>
                                <a class="pagination-btn active" href="#" aria-current="page">{{ $page }}</a>
                            </li>
                        @else
                            <li>
                                <a class="pagination-btn" href="{{ $url }}">{{ $page }}</a>
                            </li>
                        @endif
                    @endforeach
                @endif
            @endforeach

            {{-- Next Page Link --}}
            @if ($paginator->hasMorePages())
                <li>
                    <a class="next pagination-btn active" href="{{ $paginator->nextPageUrl() }}" rel="next">
                        <span class="d-none d-sm-inline">{{ __('Next') }}</span>
                        <i class="fa-solid fa-chevron-right d-inline d-sm-none"></i>
                    </a>
                </li>
            @else
                <li>
                    <a class="next disabled pagination-btn" href="#" style="pointer-events: none;">
                        <span class="d-none d-sm-inline">{{ __('Next') }}</span>
                        <i class="fa-solid fa-chevron-right d-inline d-sm-none"></i>
                    </a>
                </li>
            @endif
        </ul>
    </div>
@endif
