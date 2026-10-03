{{-- Message flash (session status) affiché dans le toast du template ; front.js le masque ensuite --}}
<div class="toast" id="toast" @unless (session('status')) hidden @endunless>@if (session('status'))✓ {{ session('status') }}@endif</div>
