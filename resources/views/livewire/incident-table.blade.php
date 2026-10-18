{{-- Composant Livewire IncidentTable : filtres et tri dans l'URL, badges « En retard » et score d'urgence IA.
     Les boutons de suppression ouvrent la modale <x-confirm-delete /> du layout (data-del + data-action). --}}
@php
    $icon = fn (string $paths) => '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">'.$paths.'</svg>';
    $eye = $icon('<path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/>');
    $edit = $icon('<path d="M12 20h9M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z"/>');
    $trash = $icon('<path d="M3 6h18M8 6V4h8v2M19 6l-1 14H6L5 6"/>');
@endphp

<div class="card">
  {{-- Sans JavaScript, le formulaire GET recharge la page avec les mêmes paramètres --}}
  <form class="filter-bar" method="GET" action="{{ route('admin.incidents.index') }}" x-on:submit.prevent>
    <input class="input min-w-[200px] flex-1" type="search" name="q" wire:model.live.debounce.300ms="q" placeholder="Référence, titre, adresse…" aria-label="Rechercher">
    <select class="input" name="type" wire:model.live="type" aria-label="Type">
      <option value="">Tous les types</option>
      @foreach (\App\Enums\IncidentType::cases() as $case)
      <option value="{{ $case->value }}">{{ $case->label() }}</option>
      @endforeach
    </select>
    <select class="input" name="severity" wire:model.live="severity" aria-label="Gravité">
      <option value="">Toutes gravités</option>
      @foreach (\App\Enums\IncidentSeverity::cases() as $case)
      <option value="{{ $case->value }}">{{ $case->label() }}</option>
      @endforeach
    </select>
    <select class="input" name="status" wire:model.live="status" aria-label="Statut">
      <option value="">Tous les statuts</option>
      @foreach (\App\Enums\IncidentStatus::cases() as $case)
      <option value="{{ $case->value }}">{{ $case->label() }}</option>
      @endforeach
    </select>
    <select class="input" name="zone" wire:model.live="zone" aria-label="Zone">
      <option value="">Toutes les zones</option>
      @foreach ($zones as $id => $name)
      <option value="{{ $id }}">{{ $name }}</option>
      @endforeach
    </select>
    <select class="input" name="infrastructure" wire:model.live="infrastructure" aria-label="Infrastructure">
      <option value="">Avec ou sans ouvrage</option>
      <option value="avec">Rattachés à un ouvrage</option>
      <option value="sans">Sans ouvrage</option>
    </select>
    <label class="flex items-center gap-1.5 text-[.85rem]"><span class="text-muted">Du</span><input class="input" type="date" name="du" wire:model.live="du" aria-label="Signalés depuis le"></label>
    <label class="flex items-center gap-1.5 text-[.85rem]"><span class="text-muted">au</span><input class="input" type="date" name="au" wire:model.live="au" aria-label="Signalés jusqu'au"></label>
    <select class="input" name="retard" wire:model.live="retard" aria-label="Délai">
      <option value="">Tous les délais</option>
      <option value="1">En retard (SLA)</option>
    </select>
    <select class="input" name="tri" wire:model.live="tri" aria-label="Tri">
      <option value="">Plus récents</option>
      <option value="anciens">Plus anciens</option>
      <option value="urgence">Urgence (IA)</option>
      <option value="gravite">Gravité</option>
    </select>
    <noscript><button class="btn btn-ghost" type="submit">Filtrer</button></noscript>
    @if ($filtered)
    <button class="btn btn-ghost" type="button" wire:click="resetFilters">Réinitialiser</button>
    @endif
    @can('export', \App\Models\Incident::class)
    <a class="btn btn-ghost" href="{{ $exportUrl }}">Export CSV</a>
    @endcan
    <span class="self-center text-[.8rem] text-muted" wire:loading.delay>Recherche…</span>
  </form>

  <div class="overflow-x-auto transition-opacity" wire:loading.class="opacity-60">
    <table class="data-table"><thead><tr><th>Incident</th><th>Type</th><th>Infrastructure · Zone</th><th>Gravité</th><th>Statut</th><th>Urgence</th><th>Date</th><th></th></tr></thead><tbody>
      @forelse ($incidents as $incident)
      <tr wire:key="incident-{{ $incident->id }}">
        <td><span class="mr-2.5 inline-block h-7 w-1 rounded-[2px] align-middle" style="background:{{ $incident->severity->color() }}"></span><span class="inline-block align-middle"><a class="font-semibold" href="{{ route('admin.incidents.show', $incident) }}">{{ $incident->title }}</a><br><span class="font-mono text-muted text-[.76rem]">{{ $incident->reference }}</span>@if ($incident->duplicate_of_id)<span class="ml-1 text-[.74rem] text-muted">· doublon</span>@elseif ($incident->reports_count > 1)<span class="ml-1 text-[.74rem] text-muted">· {{ $incident->reports_count }} signalements</span>@endif</span></td>
        <td>{{ $incident->type->icon() }} {{ $incident->type->label() }}</td>
        <td>
          @if ($incident->infrastructure)
          <a class="underline" href="{{ route('admin.infrastructures.show', $incident->infrastructure) }}">{{ $incident->infrastructure->name }}</a><br><span class="text-muted text-[.78rem]">{{ $incident->infrastructure->zone->name }}</span>
          @else
          <span class="text-muted">—</span><br><span class="text-muted text-[.78rem]">{{ $incident->address }}</span>
          @endif
        </td>
        <td><x-status-badge :value="$incident->severity->label()" :variant="$incident->severity->badge()" dot /></td>
        <td><x-status-badge :value="$incident->status->label()" :variant="$incident->status->badge()" />@if ($incident->isOverdue())<br><span class="mt-1 inline-block rounded px-1.5 text-[.72rem] font-semibold text-white" style="background:#E63946">En retard</span>@endif</td>
        <td class="font-mono">@if ($incident->ai_urgency_score)<span title="{{ $incident->ai_urgency_reason }}" @class(['font-semibold', 'text-[#E63946]' => $incident->ai_urgency_score >= 8])>{{ $incident->ai_urgency_score }}/10</span>@else<span class="text-muted">—</span>@endif</td>
        <td class="font-mono whitespace-nowrap">{{ $incident->created_at->format('d/m H:i') }}</td>
        <td><div class="flex justify-end gap-1.5">
          <a class="btn-icon" href="{{ route('admin.incidents.show', $incident) }}" title="Voir">{!! $eye !!}</a>
          <a class="btn-icon" href="{{ route('admin.incidents.edit', $incident) }}" title="Modifier">{!! $edit !!}</a>
          <button class="btn-icon btn-icon-danger" type="button" data-del="{{ $incident->reference }}" data-action="{{ route('admin.incidents.destroy', $incident) }}" title="Supprimer">{!! $trash !!}</button>
        </div></td>
      </tr>
      @empty
      <tr><td colspan="8" class="text-muted text-center p-7">Aucun incident ne correspond à ces critères.</td></tr>
      @endforelse
    </tbody></table>
  </div>

  <div class="card-footer">
    <span class="text-muted">@if ($incidents->total())Affichage {{ $incidents->firstItem() }} à {{ $incidents->lastItem() }} sur {{ $incidents->total() }}@else Aucun résultat @endif</span>
    {{ $incidents->onEachSide(1)->links('partials.livewire-pagination') }}
  </div>
</div>
