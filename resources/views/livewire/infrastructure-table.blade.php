{{-- Composant Livewire InfrastructureTable : la liste se met à jour à la saisie et au changement de filtre.
     Les boutons de suppression ouvrent la modale <x-confirm-delete /> du layout (data-del + data-action). --}}
@php
    // Icônes des actions
    $icon = fn (string $paths) => '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">'.$paths.'</svg>';
    $eye = $icon('<path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/>');
    $edit = $icon('<path d="M12 20h9M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z"/>');
    $trash = $icon('<path d="M3 6h18M8 6V4h8v2M19 6l-1 14H6L5 6"/>');
@endphp

<div class="card">
  {{-- Sans JavaScript, le formulaire GET recharge la page avec les mêmes paramètres --}}
  <form class="filter-bar" method="GET" action="{{ route('admin.infrastructures.index') }}" x-on:submit.prevent>
    <input class="input min-w-[200px] flex-1" type="search" name="q" wire:model.live.debounce.300ms="q" placeholder="Nom, code ouvrage…" aria-label="Rechercher">
    <select class="input" name="type" wire:model.live="type" aria-label="Type">
      <option value="">Tous les types</option>
      @foreach (\App\Enums\InfrastructureType::cases() as $case)
      <option value="{{ $case->value }}">{{ $case->label() }}</option>
      @endforeach
    </select>
    <select class="input" name="zone" wire:model.live="zone" aria-label="Zone">
      <option value="">Toutes les zones</option>
      @foreach ($zones as $id => $name)
      <option value="{{ $id }}">{{ $name }}</option>
      @endforeach
    </select>
    <select class="input" name="status" wire:model.live="status" aria-label="État">
      <option value="">Tous les états</option>
      @foreach (\App\Enums\InfrastructureStatus::cases() as $case)
      <option value="{{ $case->value }}">{{ $case->label() }}</option>
      @endforeach
    </select>
    <noscript><button class="btn btn-ghost" type="submit">Filtrer</button></noscript>
    @if ($filtered)
    <button class="btn btn-ghost" type="button" wire:click="resetFilters">Réinitialiser</button>
    @endif
    <span class="self-center text-[.8rem] text-muted" wire:loading.delay>Recherche…</span>
  </form>

  <div class="overflow-x-auto transition-opacity" wire:loading.class="opacity-60">
    <table class="data-table"><thead><tr><th>Code</th><th>Nom</th><th>Type</th><th>Zone</th><th>Capacité</th><th>État</th><th>Dernière inspection</th><th></th></tr></thead><tbody>
      @forelse ($infrastructures as $infrastructure)
      <tr wire:key="infra-{{ $infrastructure->id }}">
        <td class="font-mono text-[.8rem]">{{ $infrastructure->code }}</td>
        <td><a href="{{ route('admin.infrastructures.show', $infrastructure) }}"><strong>{{ $infrastructure->name }}</strong></a>@unless ($infrastructure->is_public)<br><span class="text-[.76rem] text-muted">Masquée sur la carte publique</span>@endunless</td>
        <td>{{ $infrastructure->type->label() }}</td>
        <td><a class="underline" href="{{ route('admin.zones.show', $infrastructure->zone) }}">{{ $infrastructure->zone->name }}</a></td>
        <td class="font-mono">{{ $infrastructure->formattedCapacity() }}</td>
        <td><x-status-badge :value="$infrastructure->status->label()" :variant="$infrastructure->status->badge()" dot /></td>
        <td class="font-mono">{{ $infrastructure->last_inspection_at?->format('d/m/Y') ?? '—' }}</td>
        <td><div class="flex justify-end gap-1.5">
          <a class="btn-icon" href="{{ route('admin.infrastructures.show', $infrastructure) }}" title="Voir">{!! $eye !!}</a>
          <a class="btn-icon" href="{{ route('admin.infrastructures.edit', $infrastructure) }}" title="Modifier">{!! $edit !!}</a>
          <button class="btn-icon btn-icon-danger" type="button" data-del="{{ $infrastructure->name }}" data-action="{{ route('admin.infrastructures.destroy', $infrastructure) }}" title="Supprimer">{!! $trash !!}</button>
        </div></td>
      </tr>
      @empty
      <tr><td colspan="8" class="p-7 text-center text-muted">Aucune infrastructure ne correspond à ces critères.</td></tr>
      @endforelse
    </tbody></table>
  </div>

  <div class="card-footer">
    <span class="text-muted">@if ($infrastructures->total())Affichage {{ $infrastructures->firstItem() }} à {{ $infrastructures->lastItem() }} sur {{ $infrastructures->total() }}@else Aucun résultat @endif</span>
    {{ $infrastructures->onEachSide(1)->links('livewire.partials.pagination') }}
  </div>
</div>
