{{-- Composant Livewire ProjectAssistant : questions sur les projets (outils en lecture seule, repli sans clé API) --}}
<div class="card flex flex-col gap-3 p-[22px]">
  <div><h3>Assistant projets</h3><p class="text-[.85rem] text-muted">Exemples : « Combien a été collecté pour le réservoir ? », « Quels projets sont presque financés ? »</p></div>
  @if ($messages)
  <div class="flex max-h-[320px] flex-col gap-2 overflow-y-auto" aria-live="polite">
    @foreach ($messages as $message)
    <div @class(['max-w-[90%] rounded-lg px-3 py-2 text-[.88rem]', 'self-end bg-water-soft' => $message['role'] === 'me', 'self-start bg-surface-2' => $message['role'] !== 'me'])>{{ $message['text'] }}</div>
    @endforeach
  </div>
  @endif
  <form class="flex gap-2" wire:submit="ask">
    <input class="input flex-1" wire:model="question" maxlength="500" placeholder="Posez votre question…" aria-label="Question sur les projets">
    <button class="btn btn-primary btn-sm" type="submit" wire:loading.attr="disabled" wire:target="ask">Envoyer</button>
  </form>
  @error('question')<span class="text-[.8rem] font-medium text-crit">{{ $message }}</span>@enderror
</div>
