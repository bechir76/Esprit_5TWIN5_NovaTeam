{{-- Messages flash (session status / error) ; back.js les retire ensuite et y ajoute les toasts des actions --}}
<div class="fixed right-5 bottom-[calc(20px+env(safe-area-inset-bottom,0px))] z-[60] flex flex-col gap-2.5" id="toasts" role="status">
  @if (session('status'))<div class="toast-card border-l-teal">✓ {{ session('status') }}</div>@endif
  @if (session('error'))<div class="toast-card border-l-crit">✕ {{ session('error') }}</div>@endif
</div>
