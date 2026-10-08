{{-- Message flash (session status) affiché dans le toast ; front.js le masque ensuite --}}
<div class="fixed bottom-[calc(24px+env(safe-area-inset-bottom,0px))] left-1/2 z-40 flex -translate-x-1/2 items-center gap-2.5 rounded-card bg-navy px-[18px] py-3 text-white shadow-card"
     id="toast" role="status" @unless (session('status')) hidden @endunless>@if (session('status'))✓ {{ session('status') }}@endif</div>
