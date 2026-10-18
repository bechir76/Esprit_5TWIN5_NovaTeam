@extends('layouts.layout')

{{-- Suivi d'un signalement (lien signé ou déclarant connecté) : statut, étapes, interventions prévues et
     estimation du délai. Jamais le compte rendu interne, les commentaires du personnel ni le nom des agents. --}}
@php
    $steps = collect($incident->status_history ?? [])
        ->map(fn (array $entry) => [
            'status' => \App\Enums\IncidentStatus::tryFrom($entry['to'] ?? ''),
            'at' => \Illuminate\Support\Carbon::parse($entry['at']),
        ])
        ->filter(fn (array $step) => $step['status'] !== null);
@endphp

@section('title', 'Suivi '.$incident->reference)

@section('content')
<section class="pt-10 pb-16" data-page="incident-track">
  <div class="mx-auto flex max-w-[900px] flex-col gap-5 px-5">
    <div class="flex flex-col gap-2"><span class="kicker">Suivi de votre signalement</span><h2>{{ $incident->title }}</h2>
      <div class="flex flex-wrap items-center gap-2.5"><span class="font-mono text-muted">{{ $incident->reference }}</span><x-status-badge :value="$incident->status->label()" :variant="$incident->status->badge()" /><x-status-badge :value="$incident->severity->label()" :variant="$incident->severity->badge()" dot /></div>
    </div>

    @if ($incident->duplicateOf)
    <div class="callout callout-info">Votre signalement a été rattaché à l'incident <strong class="font-mono">{{ $incident->duplicateOf->reference }}</strong> ({{ $incident->duplicateOf->title }}), statut : {{ mb_strtolower($incident->duplicateOf->status->label()) }}. Il est traité avec celui-ci.</div>
    @endif

    <div class="grid items-start gap-5 min-[861px]:grid-cols-[minmax(0,1fr)_300px]">
      <div class="card"><div class="card-header"><h2>Étapes</h2></div><div class="p-[18px] timeline">
        @forelse ($steps as $step)
        <div @class(['timeline-item', 'is-done' => ! $loop->last || ! $incident->status->isOpen(), 'pb-0' => $loop->last])><strong>{{ $loop->first ? 'Signalement reçu' : $step['status']->label() }}</strong><div class="text-muted font-mono">{{ $step['at']->format('d/m/Y H:i') }}</div></div>
        @empty
        <div class="timeline-item pb-0"><strong>Signalement reçu</strong><div class="text-muted font-mono">{{ $incident->created_at->format('d/m/Y H:i') }}</div></div>
        @endforelse
      </div></div>

      <aside class="flex flex-col gap-5">
        <div class="card p-[18px] flex flex-col gap-2.5"><h2>Détails</h2>
          <div><span class="caption block">Type</span>{{ $incident->type->icon() }} {{ $incident->type->label() }}</div>
          <div><span class="caption block">Localisation</span>{{ $incident->address }}</div>
          <div><span class="caption block">Signalé le</span><span class="font-mono">{{ $incident->created_at->format('d/m/Y H:i') }}</span></div>
          @if ($incident->resolved_at)
          <div><span class="caption block">Résolu le</span><span class="font-mono">{{ $incident->resolved_at->format('d/m/Y H:i') }}</span></div>
          @endif
        </div>
        @if ($estimate)
        <div class="card p-[18px] flex flex-col gap-1.5"><h2>Délai estimé</h2>
          <strong>Résolution {{ \App\Services\Incidents\ResolutionEstimator::label($estimate['hours']) }} après le signalement</strong>
          <span class="text-[.8rem] text-muted">{{ $estimate['basis'] === 'sla' ? 'Délai cible du service pour ce niveau de gravité.' : 'Estimation à partir des incidents comparables déjà résolus ('.$estimate['samples'].').' }} Indication, sans engagement.</span>
        </div>
        @endif
      </aside>
    </div>

    <div class="card"><div class="card-header"><h2>Interventions</h2></div>
      <div class="overflow-x-auto"><table class="data-table"><thead><tr><th>Date prévue</th><th>Intervention</th><th>Statut</th></tr></thead><tbody>
        @forelse ($incident->interventions as $intervention)
        <tr><td class="font-mono whitespace-nowrap">{{ $intervention->scheduled_at->format('d/m/Y H:i') }}</td><td>{{ $intervention->title }}</td><td><x-status-badge :value="$intervention->status->label()" :variant="$intervention->status->badge()" dot /></td></tr>
        @empty
        <tr><td colspan="3" class="p-7 text-center text-muted">Aucune intervention planifiée pour le moment.</td></tr>
        @endforelse
      </tbody></table></div>
    </div>

    <p class="text-[.85rem] text-muted">Conservez l'adresse de cette page : elle permet de suivre le signalement sans vous connecter. Vous êtes aussi prévenu par e-mail à chaque changement de statut.</p>
  </div>
</section>
@endsection
