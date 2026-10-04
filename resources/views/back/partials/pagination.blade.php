{{-- Pagination au style du template (.pager) : $users->links('back.partials.pagination') --}}
@if ($paginator->hasPages())
<nav class="pager" aria-label="Pagination">
  @if ($paginator->onFirstPage())
  <a aria-disabled="true" style="opacity:.4;pointer-events:none">‹</a>
  @else
  <a href="{{ $paginator->previousPageUrl() }}" rel="prev" aria-label="Page précédente">‹</a>
  @endif

  @foreach ($elements as $element)
    @if (is_string($element))
    <a aria-disabled="true" style="pointer-events:none">{{ $element }}</a>
    @endif
    @if (is_array($element))
      @foreach ($element as $page => $url)
      <a href="{{ $url }}" @class(['on' => $page == $paginator->currentPage()]) @if ($page == $paginator->currentPage()) aria-current="page" @endif>{{ $page }}</a>
      @endforeach
    @endif
  @endforeach

  @if ($paginator->hasMorePages())
  <a href="{{ $paginator->nextPageUrl() }}" rel="next" aria-label="Page suivante">›</a>
  @else
  <a aria-disabled="true" style="opacity:.4;pointer-events:none">›</a>
  @endif
</nav>
@endif
