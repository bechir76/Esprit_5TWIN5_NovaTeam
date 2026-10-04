{{-- Messages flash (session status / error) affichés comme les toasts du template ; back.js les retire ensuite --}}
<div class="toasts" id="toasts">@if (session('status'))<div class="toast">✓ {{ session('status') }}</div>@endif @if (session('error'))<div class="toast err">✕ {{ session('error') }}</div>@endif</div>
