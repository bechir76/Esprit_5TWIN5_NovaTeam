@extends('layouts.layout')

@section('title', $incident->title)

@php
    $icon = fn (string $paths) => '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">'.$paths.'</svg>';
    $eye = $icon('<path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/>');
    $edit = $icon('<path d="M12 20h9M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z"/>');
    $trash = $icon('<path d="M3 6h18M8 6V4h8v2M19 6l-1 14H6L5 6"/>');
    $pdf = $icon('<path d="M14 3H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9Z"/><path d="M14 3v6h6M9 15h6"/>');
@endphp

@section('content')
<section data-page="incident" class="flex flex-col gap-3">
  <x-page-header :title="$incident->title" :crumb="'Console / Incidents / '.$incident->reference">
    <x-slot:badges><x-status-badge :value="$incident->severity->label()" :variant="$incident->severity->badge()" dot /><span class="badge {{ $incident->status->badge() }}" x-data="{ label: @js($incident->status->label()), variant: @js($incident->status->badge()) }" x-on:incident-status-updated.window="$el.classList.remove(variant); variant = $event.detail.variant; $el.classList.add(variant); label = $event.detail.label" x-text="label">{{ $incident->status->label() }}</span>@if ($incident->isOverdue())<span class="badge" style="background:#E63946;color:#fff">En retard</span>@endif</x-slot:badges>
    <div class="flex flex-wrap items-center gap-2.5"><a class="btn btn-ghost" href="{{ route('admin.incidents.edit', $incident) }}">Modifier</a><button class="btn btn-danger" type="button" data-del="{{ $incident->reference }}" data-action="{{ route('admin.incidents.destroy', $incident) }}">Supprimer</button></div>
  </x-page-header>
  <div class="grid items-start gap-[18px] min-[1101px]:grid-cols-[minmax(0,1fr)_340px]">
    <div class="flex flex-col gap-[18px]">
      <div class="card p-[18px] flex flex-col gap-4">
        <div class="grid grid-fit-[170px] gap-4">
          <div><span class="caption block">Référence</span><span class="font-mono">{{ $incident->reference }}</span></div>
          <div><span class="caption block">Type</span><span>{{ $incident->type->icon() }} {{ $incident->type->label() }}</span></div>
          <div><span class="caption block">Infrastructure liée</span>
            @if ($incident->infrastructure)
            <a class="text-info-fg underline" href="{{ route('admin.infrastructures.show', $incident->infrastructure) }}">{{ $incident->infrastructure->name }}</a>
            @else
            <span>Non rattaché</span>
            @endif
          </div>
          <div><span class="caption block">Zone</span><span>{{ $incident->infrastructure?->zone->name ?? '—' }}</span></div>
          <div><span class="caption block">Localisation</span><span>{{ $incident->address }}</span></div>
          <div><span class="caption block">Déclarant</span><span>{{ $incident->user ? $incident->user->name.' · '.mb_strtolower($incident->user->role->label()) : 'Compte supprimé' }}</span></div>
          <div><span class="caption block">Signalé le</span><span class="font-mono">{{ $incident->created_at->format('d/m/Y H:i') }}</span></div>
          <div><span class="caption block">Résolu le</span><span class="font-mono">{{ $incident->resolved_at?->format('d/m/Y H:i') ?? '—' }}</span></div>
        </div>
        <div><span class="caption block">Description</span><p class="mt-1.5 max-w-[70ch]">{{ $incident->description }}</p></div>
        <div><span class="caption block">Photo</span>
          @if ($incident->photo_path)
          <div class="flex flex-wrap gap-2.5 mt-2"><a class="block h-[180px] w-[240px] max-w-full overflow-hidden rounded-lg bg-surface-2" href="{{ $incident->photoUrl() }}" target="_blank" rel="noopener"><img class="w-full h-full object-cover" src="{{ $incident->photoUrl() }}" alt="Photo jointe au signalement {{ $incident->reference }}"></a></div>
          @else
          <p class="text-muted mt-1.5">Aucune photo jointe.</p>
          @endif
        </div>
      </div>

      <div class="card">
        <div class="card-header"><h2>Interventions</h2>
          @if ($incident->status->isOpen())
          <a class="btn btn-primary btn-sm" href="{{ route('admin.interventions.create', ['incident' => $incident->id]) }}">+ Planifier une intervention</a>
          @endif
        </div>
        <div class="overflow-x-auto"><table class="data-table"><thead><tr><th>Date</th><th>Intervention</th><th>Équipe</th><th>Technicien</th><th>Durée estimée</th><th>Statut</th><th></th></tr></thead><tbody>
          @forelse ($incident->interventions as $intervention)
          <tr>
            <td class="font-mono whitespace-nowrap">{{ $intervention->scheduled_at->format('d/m H:i') }}</td>
            <td><a href="{{ route('admin.interventions.show', $intervention) }}"><strong>{{ $intervention->title }}</strong></a></td>
            <td>{{ $intervention->team }}</td>
            <td class="text-muted">{{ $intervention->technician?->name ?? '—' }}</td>
            <td class="font-mono">{{ $intervention->formattedDuration() }}</td>
            <td><x-status-badge :value="$intervention->status->label()" :variant="$intervention->status->badge()" dot /></td>
            <td><div class="flex justify-end gap-1.5">
              <a class="btn-icon" href="{{ route('admin.interventions.show', $intervention) }}" title="Voir">{!! $eye !!}</a>
              <a class="btn-icon" href="{{ route('admin.interventions.edit', $intervention) }}" title="Modifier">{!! $edit !!}</a>
              @if ($intervention->report)<a class="btn-icon" href="{{ route('admin.interventions.pdf', $intervention) }}" title="Rapport PDF">{!! $pdf !!}</a>@endif
              <button class="btn-icon btn-icon-danger" type="button" data-del="{{ $intervention->title }}" data-action="{{ route('admin.interventions.destroy', ['intervention' => $intervention, 'retour' => 'incident']) }}" title="Supprimer">{!! $trash !!}</button>
            </div></td>
          </tr>
          @empty
          <tr><td colspan="7" class="text-muted text-center p-7">Aucune intervention pour cet incident.</td></tr>
          @endforelse
        </tbody></table></div>
      </div>

      {{-- Historique des statuts (status_history, écrit par IncidentObserver) --}}
      <div class="card"><div class="card-header"><h2>Historique</h2></div><div class="p-[18px] timeline">
        @forelse ($incident->status_history ?? [] as $entry)
        @php($to = \App\Enums\IncidentStatus::tryFrom($entry['to'] ?? ''))
        @php($from = \App\Enums\IncidentStatus::tryFrom($entry['from'] ?? ''))
        <div @class(['timeline-item', 'is-done' => ! $loop->last, 'pb-0' => $loop->last])>
          <strong>{{ $loop->first ? 'Signalement reçu' : ($to?->label() ?? $entry['to']) }}</strong>@if (! $loop->first && $from)<span class="text-muted"> (depuis « {{ $from->label() }} »)</span>@endif
          @if (! empty($entry['by_name']))<span class="text-muted"> · {{ $entry['by_name'] }}</span>@endif
          <div class="text-muted font-mono text-[.8rem]">{{ \Illuminate\Support\Carbon::parse($entry['at'])->format('d/m/Y H:i') }}</div>
          @if (! empty($entry['comment']))<div class="mt-1 text-[.86rem]">« {{ $entry['comment'] }} »</div>@endif
        </div>
        @empty
        <div class="timeline-item pb-0"><strong>Signalement reçu</strong><div class="text-muted">{{ $incident->created_at->format('d/m H:i') }}</div></div>
        @endforelse
      </div></div>

      @if ($incident->duplicateOf || $incident->duplicates->isNotEmpty() || $linked->isNotEmpty())
      <div class="card p-[18px] flex flex-col gap-3"><h2>Signalements liés</h2>
        @if ($incident->duplicateOf)
        <p>Doublon de <a class="underline font-mono" href="{{ route('admin.incidents.show', $incident->duplicateOf) }}">{{ $incident->duplicateOf->reference }}</a> · {{ $incident->duplicateOf->title }}</p>
        @endif
        @if ($incident->duplicates->isNotEmpty())
        <div><span class="caption block">Doublons rattachés ({{ $incident->reports_count }} signalements au total)</span>
          <div class="mt-1.5 flex flex-wrap gap-2 text-[.82rem]">@foreach ($incident->duplicates as $duplicate)<a class="rounded bg-surface-2 px-2 py-1 font-mono" href="{{ route('admin.incidents.show', $duplicate) }}">{{ $duplicate->reference }}</a>@endforeach</div></div>
        @endif
        @if ($linked->isNotEmpty())
        <div><span class="caption block">Incidents proches du même groupe ({{ $incident->cluster_key }})</span>
          @if ($incident->cluster_diagnosis)<p class="mt-1 text-[.88rem]">✦ {{ $incident->cluster_diagnosis }}</p>@endif
          <div class="mt-1.5 flex flex-wrap gap-2 text-[.82rem]">@foreach ($linked as $other)<a class="rounded bg-surface-2 px-2 py-1 font-mono" href="{{ route('admin.incidents.show', $other) }}">{{ $other->reference }} · {{ $other->type->label() }}</a>@endforeach</div>
          @if ($incident->status->isOpen())
          <form class="mt-2.5" method="POST" action="{{ route('admin.incidents.group', $incident->cluster_key) }}">@csrf<button class="btn btn-ghost btn-sm" type="submit">Regrouper les interventions</button></form>
          @endif
        </div>
        @endif
      </div>
      @endif
    </div>
    <aside class="flex flex-col gap-[18px]">
      {{-- Changement de statut sans rechargement : composant Livewire (app/Livewire/IncidentStatus.php) --}}
      <livewire:incident-status :incident="$incident" />
      @if ($incident->canSuggestResolution())
      <div class="callout callout-info">Toutes les interventions sont terminées : l'incident peut sans doute passer « Résolu ».</div>
      @endif
      <div class="card p-[18px] flex flex-col gap-2.5"><h2>Délai</h2>
        <div><span class="caption block">Délai cible ({{ $incident->severity->label() }})</span><span class="font-mono">{{ $incident->slaHours() }} h · échéance {{ $incident->slaDeadline()->format('d/m/Y H:i') }}</span></div>
        @if ($incident->isOverdue())
        <span class="font-semibold text-crit">Délai cible dépassé{{ $incident->sla_breached_at ? ' (alerte envoyée le '.$incident->sla_breached_at->format('d/m H:i').')' : '' }}</span>
        @endif
        @if ($estimate)
        <div><span class="caption block">Résolution estimée</span><span>{{ \App\Services\Incidents\ResolutionEstimator::label($estimate['hours']) }} après le signalement</span>
          <div class="text-[.76rem] text-muted">{{ match ($estimate['basis']) { 'type_severity' => 'Médiane de '.$estimate['samples'].' incidents de même type et gravité.', 'global' => 'Médiane de '.$estimate['samples'].' incidents résolus.', default => 'Historique insuffisant : délai cible.' } }}</div></div>
        @endif
      </div>
      {{-- B1 / B2 / B6 : analyse IA en arrière-plan (AnalyzeIncident) ; indicative, les agents décident --}}
      <div class="card p-[18px] flex flex-col gap-2.5"><h2>✦ Analyse IA</h2>
        @if ($incident->ai_analyzed_at)
        <div class="flex items-center gap-2.5"><strong @class(['font-mono text-xl', 'text-crit' => $incident->ai_urgency_score >= 8])>{{ $incident->ai_urgency_score }}/10</strong><span class="text-[.82rem] text-muted">score d'urgence</span></div>
        @if ($incident->ai_urgency_reason)<p class="text-[.84rem] text-muted">{{ $incident->ai_urgency_reason }}</p>@endif
        @if ($incident->ai_summary)<p class="text-[.9rem]">{{ $incident->ai_summary }}</p>@endif
        @php($aiType = \App\Enums\IncidentType::tryFrom((string) $incident->ai_type))
        @php($aiSeverity = \App\Enums\IncidentSeverity::tryFrom((string) $incident->ai_severity))
        @if ($aiType && $aiSeverity)
        <div class="text-[.84rem]">Suggestion : {{ $aiType->label() }} · {{ $aiSeverity->label() }}@if ($aiType !== $incident->type || $aiSeverity !== $incident->severity) <span class="text-muted">(différente des valeurs retenues)</span>@endif</div>
        @endif
        @if ($incident->ai_photo_analysis)<p class="text-[.84rem]">{{ $incident->ai_photo_analysis }}</p>@endif
        <span class="text-[.74rem] text-muted">Analysé le {{ $incident->ai_analyzed_at->format('d/m/Y H:i') }}</span>
        @else
        <p class="text-[.86rem] text-muted">Analyse en attente (file de traitement).</p>
        @endif
      </div>
      <div class="card p-[18px] flex flex-col gap-3"><h2>Localisation</h2>
        <x-location-map :latitude="$incident->latitude" :longitude="$incident->longitude" :color="$incident->severity->color()" :height="160" />
        @if ($incident->hasCoordinates())
        <span class="font-mono text-muted text-[.8rem]">{{ number_format($incident->latitude, 5, '.', '') }}, {{ number_format($incident->longitude, 5, '.', '') }}</span>
        @endif
      </div>
      @if ($incident->infrastructure)
      <div class="card p-[18px] flex flex-col gap-3"><h2>Infrastructure</h2>
        <div class="grid grid-fit-[170px] gap-2">
          <div><span class="caption block">Code</span><span class="font-mono">{{ $incident->infrastructure->code }}</span></div>
          <div><span class="caption block">Type</span><span>{{ $incident->infrastructure->type->label() }}</span></div>
          <div><span class="caption block">État</span><x-status-badge :value="$incident->infrastructure->status->label()" :variant="$incident->infrastructure->status->badge()" dot /></div>
        </div>
        <a class="btn btn-ghost justify-center mt-1.5" href="{{ route('admin.infrastructures.show', $incident->infrastructure) }}">Voir l'infrastructure</a>
      </div>
      @endif
    </aside>
  </div>
</section>
@endsection
