{{-- Composant Livewire InfrastructureTable : la liste se met à jour à la saisie, au changement de filtre et de tri.
     Colonne « Risque » : score prédictif (RiskScorer) ; badge « Inspection en retard » (Infrastructure::isInspectionOverdue()).
     Les boutons de suppression ouvrent la modale <x-confirm-delete /> du layout (data-del + data-action). --}}
@php
    // Icônes des actions
    $icon = fn (string $paths) => '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">'.$paths.'</svg>';
    $eye = $icon('<path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/>');
    $edit = $icon('<path d="M12 20h9M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z"/>');
    $trash = $icon('<path d="M3 6h18M8 6V4h8v2M19 6l-1 14H6L5 6"/>');
@endphp

<div class="card">
  {{-- B2 : recherche en langage naturel, traduite en filtres autorisés (InfrastructureAiService::parseSearch) --}}
  <form class="flex flex-col gap-2 border-b border-line p-[14px]" wire:submit="interpret">
    <label class="text-[.86rem] font-semibold" for="nlSearch">Rechercher en langage naturel</label>
    <div class="flex flex-wrap gap-2">
      <input class="input min-w-[240px] flex-1" id="nlSearch" type="search" wire:model="sentence" maxlength="200" placeholder="Ex. : réservoirs de plus de 40 ans à risque élevé avec inspection en retard">
      <button class="btn btn-navy" type="submit" wire:loading.attr="disabled" wire:target="interpret">
        <span wire:loading.remove wire:target="interpret">Interpréter</span><span wire:loading wire:target="interpret">Analyse…</span>
      </button>
    </div>
    @error('sentence')<p class="text-[.84rem] text-crit-fg" role="alert">{{ $message }}</p>@enderror
    @if ($interpreted !== null)
    <div class="flex flex-wrap items-center gap-1.5 text-[.84rem]" aria-live="polite">
      <span class="text-muted">« {{ $interpreted }} » →</span>
      @forelse ($chips as $property => $label)
      <span class="inline-flex items-center gap-1 rounded-full bg-water-soft px-2.5 py-1 text-info-fg">{{ $label }}<button class="font-bold" type="button" wire:click="removeFilter('{{ $property }}')" aria-label="Retirer le filtre {{ $label }}">×</button></span>
      @empty
      <span class="text-muted">aucun filtre reconnu</span>
      @endforelse
      <x-status-badge :value="$interpretedBy === 'ia' ? 'interprété par l’IA' : 'mots-clés'" :variant="$interpretedBy === 'ia' ? 'b-info' : 'b-mute'" />
    </div>
    @endif
  </form>

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
    <select class="input" name="risk" wire:model.live="risk" aria-label="Niveau de risque">
      <option value="">Tous les risques</option>
      @foreach (\App\Enums\RiskLevel::cases() as $case)
      <option value="{{ $case->value }}">Risque {{ mb_strtolower($case->label()) }}</option>
      @endforeach
    </select>
    <select class="input" name="inspection" wire:model.live="inspection" aria-label="Inspection">
      <option value="">Toutes les inspections</option>
      <option value="overdue">Inspection en retard</option>
    </select>
    <select class="input" name="sort" wire:model.live="sort" aria-label="Tri">
      <option value="">Trier par code</option>
      <option value="risk">Trier par risque</option>
    </select>
    <noscript><button class="btn btn-ghost" type="submit">Filtrer</button></noscript>
    @if ($filtered)
    <button class="btn btn-ghost" type="button" wire:click="resetFilters">Réinitialiser</button>
    @endif
    <span class="self-center text-[.8rem] text-muted" wire:loading.delay>Recherche…</span>
    @if ($exportUrl)
    <a class="btn btn-ghost ml-auto" href="{{ $exportUrl }}" title="Export CSV de la liste filtrée">Exporter CSV</a>
    @endif
  </form>

  <div class="overflow-x-auto transition-opacity" wire:loading.class="opacity-60">
    <table class="data-table"><thead><tr><th>Code</th><th>Nom</th><th>Type</th><th>Zone</th><th>Capacité</th><th>État</th><th><a class="inline-flex items-center gap-1" href="{{ route('admin.infrastructures.index', ['sort' => $sort === 'risk' ? null : 'risk']) }}" wire:click.prevent="sortBy('risk')" title="Trier par risque">Risque{{ $sort === 'risk' ? ' ↓' : '' }}</a></th><th>Prochaine inspection</th><th></th></tr></thead><tbody>
      @forelse ($infrastructures as $infrastructure)
      <tr wire:key="infra-{{ $infrastructure->id }}">
        <td class="font-mono text-[.8rem]">{{ $infrastructure->code }}</td>
        <td><a href="{{ route('admin.infrastructures.show', $infrastructure) }}"><strong>{{ $infrastructure->name }}</strong></a>@unless ($infrastructure->is_public)<br><span class="text-[.76rem] text-muted">Masquée sur la carte publique</span>@endunless</td>
        <td>{{ $infrastructure->type->label() }}</td>
        <td><a class="underline" href="{{ route('admin.zones.show', $infrastructure->zone) }}">{{ $infrastructure->zone->name }}</a></td>
        <td class="font-mono">{{ $infrastructure->formattedCapacity() }}</td>
        <td><x-status-badge :value="$infrastructure->status->label()" :variant="$infrastructure->status->badge()" dot /></td>
        <td>@if ($infrastructure->risk_score !== null)<span class="inline-flex items-center gap-2"><span class="font-mono">{{ $infrastructure->risk_score }}</span><x-status-badge :value="$infrastructure->risk_level->label()" :variant="$infrastructure->risk_level->badge()" /></span>@else<span class="text-muted">Non évalué</span>@endif</td>
        <td class="font-mono">{{ $infrastructure->next_inspection_at?->format('d/m/Y') ?? '—' }}@if ($infrastructure->isInspectionOverdue())<br><x-status-badge value="Inspection en retard" variant="b-crit" />@endif</td>
        <td><div class="flex justify-end gap-1.5">
          <a class="btn-icon" href="{{ route('admin.infrastructures.show', $infrastructure) }}" title="Voir">{!! $eye !!}</a>
          <a class="btn-icon" href="{{ route('admin.infrastructures.edit', $infrastructure) }}" title="Modifier">{!! $edit !!}</a>
          <button class="btn-icon btn-icon-danger" type="button" data-del="{{ $infrastructure->name }}" data-action="{{ route('admin.infrastructures.destroy', $infrastructure) }}" title="Supprimer">{!! $trash !!}</button>
        </div></td>
      </tr>
      @empty
      <tr><td colspan="9" class="p-7 text-center text-muted">Aucune infrastructure ne correspond à ces critères.</td></tr>
      @endforelse
    </tbody></table>
  </div>

  <div class="card-footer">
    <span class="text-muted">@if ($infrastructures->total())Affichage {{ $infrastructures->firstItem() }} à {{ $infrastructures->lastItem() }} sur {{ $infrastructures->total() }}@else Aucun résultat @endif</span>
    {{ $infrastructures->onEachSide(1)->links('partials.livewire-pagination') }}
  </div>
</div>
