{{-- Messages flash (session status / error) ; back.js les retire ensuite et y ajoute les toasts des actions --}}
@php($toast = 'min-w-[260px] max-w-[360px] rounded-[9px] border border-l-4 border-line bg-surface px-4 py-3 text-[.88rem] shadow-toast')
<div class="fixed right-5 bottom-[calc(20px+env(safe-area-inset-bottom,0px))] z-[60] flex flex-col gap-2.5" id="toasts" role="status">
  @if (session('status'))<div class="{{ $toast }} border-l-teal">✓ {{ session('status') }}</div>@endif
  @if (session('error'))<div class="{{ $toast }} border-l-crit">✕ {{ session('error') }}</div>@endif
</div>
