{{-- Pagination du back office : $users->links('back.partials.pagination') --}}
@php($page = 'grid h-[30px] min-w-[30px] place-items-center rounded-[7px] border border-line px-1 text-[.8rem]')
@if ($paginator->hasPages())
<nav class="flex gap-1" aria-label="Pagination">
  @if ($paginator->onFirstPage())
  <span class="{{ $page }} opacity-40" aria-disabled="true">‹</span>
  @else
  <a class="{{ $page }} hover:bg-surface-2" href="{{ $paginator->previousPageUrl() }}" rel="prev" aria-label="Page précédente">‹</a>
  @endif

  @foreach ($elements as $element)
    @if (is_string($element))
    <span class="{{ $page }}" aria-disabled="true">{{ $element }}</span>
    @endif
    @if (is_array($element))
      @foreach ($element as $number => $url)
      @if ($number == $paginator->currentPage())
      <span class="{{ $page }} border-navy bg-navy text-white" aria-current="page">{{ $number }}</span>
      @else
      <a class="{{ $page }} hover:bg-surface-2" href="{{ $url }}">{{ $number }}</a>
      @endif
      @endforeach
    @endif
  @endforeach

  @if ($paginator->hasMorePages())
  <a class="{{ $page }} hover:bg-surface-2" href="{{ $paginator->nextPageUrl() }}" rel="next" aria-label="Page suivante">›</a>
  @else
  <span class="{{ $page }} opacity-40" aria-disabled="true">›</span>
  @endif
</nav>
@endif
