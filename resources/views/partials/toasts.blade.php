{{-- Messages flash (session status / error) : pile de toasts de la console, toast unique du site citoyen ;
     back.js et front.js les masquent ensuite --}}
@if ($admin)
<div class="fixed right-5 bottom-[calc(20px+env(safe-area-inset-bottom,0px))] z-[60] flex flex-col gap-2.5" id="toasts" role="status">
  @if (session('status'))<div class="toast-card border-l-teal">✓ {{ session('status') }}</div>@endif
  @if (session('error'))<div class="toast-card border-l-crit">✕ {{ session('error') }}</div>@endif
</div>
@else
<div class="fixed bottom-[calc(24px+env(safe-area-inset-bottom,0px))] left-1/2 z-40 flex -translate-x-1/2 items-center gap-2.5 rounded-card bg-navy px-[18px] py-3 text-white shadow-card"
     id="toast" role="status" @unless (session('status')) hidden @endunless>@if (session('status'))✓ {{ session('status') }}@endif</div>
@endif
