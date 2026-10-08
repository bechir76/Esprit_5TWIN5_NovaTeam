{{-- Composant Livewire IncidentStatus : sans JavaScript, le formulaire est envoyé à la route PATCH admin.incidents.status --}}
<form class="card flex flex-col gap-3 p-[18px]" method="POST" action="{{ route('admin.incidents.status', $incident) }}" wire:submit="save" novalidate>
  @csrf
  @method('PATCH')
  <h2>Traitement</h2>
  <x-form.select name="status" label="Statut" :value="$status" :options="$options" wire:model="status" hint="Le passage à « Résolu » enregistre la date de résolution." />
  @if ($incident->resolved_at)
  <span class="text-[.78rem] text-muted">Résolu le <span class="font-mono">{{ $incident->resolved_at->format('d/m/Y H:i') }}</span></span>
  @endif
  <button class="btn btn-primary justify-center" type="submit" wire:loading.attr="disabled" wire:target="save">
    <span wire:loading.remove wire:target="save">Mettre à jour</span>
    <span wire:loading wire:target="save">Enregistrement…</span>
  </button>
  @if ($message)
  <div class="callout callout-info" role="status" wire:key="msg-{{ md5($message.$incident->updated_at) }}">✓ {{ $message }}</div>
  @endif
</form>
