{{-- Panneau bleu des écrans connexion / inscription (masqué sous 860px) : $title, $text, $note (HTML facultatif) --}}
<div class="relative hidden flex-col justify-between gap-[30px] overflow-hidden bg-hero p-11 text-white min-[861px]:flex">
  <a class="flex items-center gap-2.5 font-display text-[1.15rem] font-extrabold text-white" href="{{ route('home') }}">
    <svg width="30" height="30" viewBox="0 0 32 32" aria-hidden="true"><path d="M16 3C16 3 6 14.5 6 20.5a10 10 0 0 0 20 0C26 14.5 16 3 16 3Z" fill="#1CA7EC"/></svg>AquaSecure
  </a>
  <div>
    <h2 class="text-white">{{ $title }}</h2>
    <p class="mt-3 max-w-[40ch] text-[#C7DDEB]">{{ $text }}</p>
  </div>
  @if (! empty($note))
  <div class="rounded-panel border border-white/16 bg-white/7 p-[22px] text-[.9rem] backdrop-blur-xs">{!! $note !!}</div>
  @else
  <div></div>
  @endif
</div>
