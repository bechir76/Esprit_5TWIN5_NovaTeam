{{-- Assistant du site citoyen : Alpine ouvre / ferme la fenêtre, front.js ajoute les messages (réponses simulées) --}}
@php
    $bubble = [
        'bot' => 'max-w-[85%] self-start rounded-xl rounded-bl-sm bg-surface-2 px-[13px] py-2.5',
        'me' => 'max-w-[85%] self-end rounded-xl rounded-br-sm bg-water px-[13px] py-2.5 text-[#032A3F]',
    ];
@endphp

<div x-data="{ open: false }" @keydown.escape.window="open = false">
  <div class="fixed right-[22px] bottom-[calc(86px+env(safe-area-inset-bottom,0px))] z-30 flex w-[360px] max-w-[calc(100vw-32px)] flex-col overflow-hidden rounded-panel border border-line bg-surface shadow-[0_18px_50px_rgba(11,60,93,.25)]"
       id="chatWin" x-show="open" x-cloak x-transition>
    <div class="flex items-center justify-between bg-navy px-4 py-3.5 text-white">
      <div><strong>Assistant AquaSecure</strong><div class="text-[.78rem] text-[#9CBCD1]">IA · répond en quelques secondes</div></div>
      <button class="text-[1.2rem] text-white" type="button" @click="open = false" aria-label="Fermer">×</button>
    </div>
    <div class="flex max-h-80 flex-col gap-2.5 overflow-auto p-4 text-[.9rem]" id="chatBody">
      <div class="{{ $bubble['bot'] }}">Bonjour{{ auth()->check() ? ' '.Str::before(auth()->user()->name, ' ') : '' }} ! Je peux vous renseigner sur la qualité de l'eau, les coupures prévues ou l'état d'un signalement.</div>
      <div class="{{ $bubble['me'] }}">L'eau du robinet a un goût de chlore, c'est normal ?</div>
      <div class="{{ $bubble['bot'] }}">Oui. Le relevé de ce matin à Ariana indique 0,42 mg/L de chlore résiduel, dans la norme (0,1 à 0,5 mg/L). Laissez l'eau reposer 30 minutes au réfrigérateur pour atténuer le goût.</div>
      <div class="flex flex-wrap gap-1.5">
        @foreach (['Coupures prévues ?', 'Statut de mon signalement', 'L\'eau est trouble'] as $chip)
        <button class="rounded-full border border-line bg-surface px-2.5 py-1.5 text-[.78rem] text-ink" type="button" data-chip>{{ $chip }}</button>
        @endforeach
      </div>
    </div>
    <form class="flex gap-2 border-t border-line p-3" id="chatForm">
      <input class="input" id="chatIn" placeholder="Posez votre question…" aria-label="Message">
      <button class="btn btn-primary btn-sm" type="submit">Envoyer</button>
    </form>
  </div>

  <button class="fixed right-[22px] bottom-[calc(22px+env(safe-area-inset-bottom,0px))] z-30 flex items-center gap-2.5 rounded-full bg-navy py-3 pr-[18px] pl-3 font-semibold text-white shadow-[0_8px_24px_rgba(11,60,93,.35)]"
          type="button" @click="open = ! open" :aria-expanded="open">
    <span class="grid size-[34px] place-items-center rounded-full bg-water">✦</span>Assistant AquaSecure
  </button>
</div>
