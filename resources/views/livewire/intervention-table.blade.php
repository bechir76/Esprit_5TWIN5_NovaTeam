{{-- Composant Livewire InterventionTable : filtres statut / date / équipe et tri, conservés dans l'URL. --}}
@php
    $icon = fn (string $paths) => '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">'.$paths.'</svg>';
    $eye = $icon('<path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/>');
    $edit = $icon('<path d="M12 20h9M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z"/>');
    $trash = $icon('<path d="M3 6h18M8 6V4h8v2M19 6l-1 14H6L5 6"/>');
@endphp

<div class="card">
  <form class="filter-bar" method="GET" action="{{ route('admin.interventions.index') }}" x-on:submit.prevent>
    <input class="input min-w-[200px] flex-1" type="search" name="q" wire:model.live.debounce.300ms="q" placeholder="Intitulé, équipe, référence incident…" aria-label="Rechercher">
    <select class="input" name="status" wire:model.live="status" aria-label="Statut">
      <option value="">Tous les statuts</option>
      @foreach (\App\Enums\InterventionStatus::cases() as $case)
      <option value="{{ $case->value }}">{{ $case->label() }}</option>
      @endforeach
    </select>
    <select class="input" name="team" wire:model.live="team" aria-label="Équipe">
      <option value="">Toutes les équipes</option>
      @foreach (\App\Models\Intervention::TEAMS as $name)
      <option value="{{ $name }}">{{ $name }}</option>
      @endforeach
    </select>
    <input class="input" type="date" name="date" wire:model.live="date" aria-label="Date prévue">
    <select class="input" name="tri" wire:model.live="tri" aria-label="Tri">
      <option value="">Plus récentes</option>
      <option value="anciennes">Plus anciennes</option>
      <option value="prochaines">Prochaines (actives)</option>
    </select>
    <noscript><button class="btn btn-ghost" type="submit">Filtrer</button></noscript>
    @if ($filtered)
    <button class="btn btn-ghost" type="button" wire:click="resetFilters">Réinitialiser</button>
    @endif
    <span class="self-center text-[.8rem] text-muted" wire:loading.delay>Recherche…</span>
  </form>

  <div class="overflow-x-auto transition-opacity" wire:loading.class="opacity-60">
    <table class="data-table"><thead><tr><th>Date</th><th>Intervention</th><th>Incident lié</th><th>Équipe</th><th>Technicien</th><th>Durée estimée</th><th>Statut</th><th></th></tr></thead><tbody>
      @forelse ($interventions as $intervention)
      <tr wire:key="intervention-{{ $intervention->id }}">
        <td class="font-mono whitespace-nowrap">{{ $intervention->scheduled_at->format('d/m H:i') }}</td>
        <td><a href="{{ route('admin.interventions.show', $intervention) }}"><strong>{{ $intervention->title }}</strong></a>@if ($intervention->incident->infrastructure)<br><span class="text-muted text-[.78rem]">{{ $intervention->incident->infrastructure->name }}</span>@endif</td>
        <td class="font-mono"><a class="underline" href="{{ route('admin.incidents.show', $intervention->incident) }}">{{ $intervention->incident->reference }}</a></td>
        <td>{{ $intervention->team }}</td>
        <td class="text-muted">{{ $intervention->technician?->name ?? '—' }}</td>
        <td class="font-mono">{{ $intervention->formattedDuration() }}</td>
        <td><x-status-badge :value="$intervention->status->label()" :variant="$intervention->status->badge()" dot /></td>
        <td><div class="flex justify-end gap-1.5">
          <a class="btn-icon" href="{{ route('admin.interventions.show', $intervention) }}" title="Voir">{!! $eye !!}</a>
          <a class="btn-icon" href="{{ route('admin.interventions.edit', $intervention) }}" title="Modifier">{!! $edit !!}</a>
          <button class="btn-icon btn-icon-danger" type="button" data-del="{{ $intervention->title }}" data-action="{{ route('admin.interventions.destroy', $intervention) }}" title="Supprimer">{!! $trash !!}</button>
        </div></td>
      </tr>
      @empty
      <tr><td colspan="8" class="text-muted text-center p-7">Aucune intervention ne correspond à ces critères.</td></tr>
      @endforelse
    </tbody></table>
  </div>

  <div class="card-footer">
    <span class="text-muted">@if ($interventions->total())Affichage {{ $interventions->firstItem() }} à {{ $interventions->lastItem() }} sur {{ $interventions->total() }}@else Aucun résultat @endif</span>
    {{ $interventions->onEachSide(1)->links('partials.livewire-pagination') }}
  </div>
</div>
