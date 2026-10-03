{{-- Message flash (session status) affiché comme un toast du template ; back.js le retire ensuite --}}
<div class="toasts" id="toasts">@if (session('status'))<div class="toast">✓ {{ session('status') }}</div>@endif</div>
