@extends('layouts.layout')

@section('title', $project->title)

@php
    $icon = fn (string $paths) => '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">'.$paths.'</svg>';
    $eye = $icon('<path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/>');
    $edit = $icon('<path d="M12 20h9M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z"/>');
    $trash = $icon('<path d="M3 6h18M8 6V4h8v2M19 6l-1 14H6L5 6"/>');
    [$priorityLabel, $priorityBadge] = \App\Services\ProjectScoring::level($project->ai_priority_score);
    $events = ['created' => 'création', 'updated' => 'modification', 'deleted' => 'suppression'];
@endphp

@section('content')
<section data-page="projet" class="flex flex-col gap-3">
  <x-page-header :title="$project->title" :crumb="'Console / Projets / '.$project->title">
    <x-slot:badges><x-status-badge :value="$project->category->label()" :variant="$project->category->badge()" /><x-status-badge :value="$project->status->label()" :variant="$project->status->badge()" /></x-slot:badges>
    <div class="flex flex-wrap items-center gap-2.5">
      @if ($project->isPublished())
      <a class="btn btn-ghost" href="{{ route('projects.show', $project) }}" target="_blank" rel="noopener">Voir sur le site</a>
      @endif
      <a class="btn btn-ghost" href="{{ route('admin.projects.edit', $project) }}">Modifier</a><button class="btn btn-danger" type="button" data-del="{{ $project->title }}" data-action="{{ route('admin.projects.destroy', $project) }}">Supprimer</button>
    </div>
  </x-page-header>
  <div class="grid items-start gap-[18px] min-[1101px]:grid-cols-[minmax(0,1fr)_340px]">
    <div class="flex flex-col gap-[18px]">
      <div class="card p-[18px] flex flex-col gap-4">
        <div class="grid grid-fit-[170px] gap-4">
          <div><span class="caption block">Catégorie</span><span>{{ $project->category->label() }}</span></div>
          <div><span class="caption block">Infrastructure</span>
            @if ($project->infrastructure)
            <a class="text-info-fg underline" href="{{ route('admin.infrastructures.show', $project->infrastructure) }}">{{ $project->infrastructure->code }} · {{ $project->infrastructure->name }}</a>
            @else
            <span>Multi-sites</span>
            @endif
          </div>
          <div><span class="caption block">Zone</span><span>{{ $project->infrastructure?->zone->name ?? '—' }}</span></div>
          <div><span class="caption block">Début de la collecte</span><span class="font-mono">{{ $project->start_date->format('d/m/Y') }}</span></div>
          <div><span class="caption block">Date limite</span><span class="font-mono">{{ $project->end_date->format('d/m/Y') }}</span></div>
          <div><span class="caption block">Jours restants</span><span class="font-mono">{{ $project->isExpired() ? 'Échue' : $project->days_left.' j' }}</span></div>
          <div><span class="caption block">Site public</span><span>{{ $project->is_public ? 'Publié' : 'Non publié' }}</span></div>
          <div><span class="caption block">Adresse publique</span><span class="font-mono text-[.8rem]">/projets/{{ $project->slug }}</span></div>
        </div>
        <div><span class="caption block">Description</span><p class="mt-1.5 max-w-[70ch]">{{ $project->description }}</p></div>
        <div><span class="caption block">Image</span>
          @if ($project->image_path)
          <div class="flex flex-wrap gap-2.5 mt-2"><a class="block h-[180px] w-[240px] max-w-full overflow-hidden rounded-lg bg-surface-2" href="{{ $project->imageUrl() }}" target="_blank" rel="noopener"><img class="w-full h-full object-cover" src="{{ $project->imageUrl() }}" alt="Image du projet {{ $project->title }}"></a></div>
          @else
          <p class="text-muted mt-1.5">Aucune image.</p>
          @endif
        </div>
      </div>

      {{-- A10 / B3 : cumul des dons confirmés contre la courbe idéale jusqu'à la date limite --}}
      <div class="card">
        <div class="card-header"><h2>Rythme de financement</h2><span @class(['badge', 'b-ok' => $forecast['on_time'], 'b-warn' => ! $forecast['on_time']])>Probabilité {{ $forecast['probability'] }}</span></div>
        <div class="p-[18px] flex flex-col gap-3">
          <p class="text-[.9rem]"><strong>{{ $forecast['message'] }}</strong> <span class="text-muted">Rythme moyen sur {{ config('projects.forecast_window_days') }} jours : {{ \App\Models\Project::money($forecast['daily_rate']) }} DT / jour.</span></p>
          <div class="h-[260px]"><canvas id="fundingChart" data-series="{{ json_encode($series) }}" role="img" aria-label="Cumul des dons confirmés et courbe idéale"></canvas></div>
        </div>
      </div>

      <div class="card">
        <div class="card-header"><h2>Financements</h2><a class="btn btn-primary btn-sm" href="{{ route('admin.fundings.create', ['projet' => $project->id]) }}">+ Ajouter un financement</a></div>
        <div class="overflow-x-auto"><table class="data-table"><thead><tr><th>Date</th><th>Référence</th><th>Source</th><th>Financeur</th><th class="text-right">Montant</th><th>Statut</th><th></th></tr></thead><tbody>
          @forelse ($project->fundings as $funding)
          <tr>
            <td class="font-mono whitespace-nowrap">{{ $funding->funded_at->format('d/m/Y') }}</td>
            <td><a class="font-mono text-[.82rem]" href="{{ route('admin.fundings.show', $funding) }}">{{ $funding->reference }}</a></td>
            <td><x-status-badge :value="$funding->source_type->label()" :variant="$funding->source_type->badge()" /></td>
            <td>{{ $funding->donor_name }}@if ($funding->user)<br><span class="text-muted text-[.78rem]">{{ $funding->user->email }}</span>@endif</td>
            <td class="font-mono text-right whitespace-nowrap">{{ \App\Models\Project::money($funding->amount) }} DT</td>
            <td><x-status-badge :value="$funding->status->label()" :variant="$funding->status->badge()" />@if ($funding->is_matching)<br><span class="text-[.74rem] text-muted">cofinancement</span>@endif @if ($funding->is_anonymous)<br><span class="text-[.74rem] text-muted">anonyme</span>@endif</td>
            <td><div class="flex justify-end gap-1.5">
              <a class="btn-icon" href="{{ route('admin.fundings.show', $funding) }}" title="Voir">{!! $eye !!}</a>
              <a class="btn-icon" href="{{ route('admin.fundings.edit', $funding) }}" title="Modifier">{!! $edit !!}</a>
              @unless ($funding->stripe_payment_intent_id)
              <button class="btn-icon btn-icon-danger" type="button" data-del="{{ $funding->reference }}" data-action="{{ route('admin.fundings.destroy', ['funding' => $funding, 'retour' => 'projet']) }}" title="Supprimer">{!! $trash !!}</button>
              @endunless
            </div></td>
          </tr>
          @empty
          <tr><td colspan="7" class="text-muted text-center p-7">Aucun financement pour ce projet.</td></tr>
          @endforelse
        </tbody></table></div>
      </div>

      {{-- A7 : journal d'audit (spatie/laravel-activitylog) --}}
      <div class="card">
        <div class="card-header"><h2>Journal d'audit</h2></div>
        <div class="overflow-x-auto"><table class="data-table"><thead><tr><th>Date</th><th>Qui</th><th>Action</th><th>Avant → après</th></tr></thead><tbody>
          @forelse ($activities as $activity)
          <tr>
            <td class="font-mono whitespace-nowrap text-[.8rem]">{{ $activity->created_at->format('d/m/Y H:i') }}</td>
            <td>{{ $activity->causer?->name ?? 'Système' }}</td>
            <td>{{ $activity->log_name === 'fundings' ? 'Financement' : 'Projet' }} : {{ $events[$activity->event] ?? $activity->event }}</td>
            <td class="text-[.8rem]">
              @foreach ($activity->properties['attributes'] ?? [] as $key => $value)
              <span class="font-mono">{{ $key }}</span> : {{ is_scalar($activity->properties['old'][$key] ?? null) ? $activity->properties['old'][$key] : '—' }} → {{ is_scalar($value) ? $value : '—' }}@unless ($loop->last)<br>@endunless
              @endforeach
            </td>
          </tr>
          @empty
          <tr><td colspan="4" class="text-muted text-center p-7">Aucune modification enregistrée.</td></tr>
          @endforelse
        </tbody></table></div>
      </div>
    </div>
    <aside class="flex flex-col gap-[18px]">
      <div class="card p-[18px] flex flex-col gap-3"><h2>Financement</h2>
        <div><strong class="font-mono text-[1.5rem]">{{ \App\Models\Project::money($project->collected_amount) }} DT</strong><div class="text-muted text-[.86rem]">collectés sur {{ \App\Models\Project::money($project->target_budget) }} DT (dons confirmés)</div></div>
        <div class="progress-track"><span class="progress-bar" style="width:{{ $project->progress_percent }}%"></span></div>
        <div class="flex flex-wrap items-center gap-2.5 justify-between text-[.86rem]"><span><strong>{{ $project->progress_percent }} %</strong> financé</span><span class="text-muted">{{ $project->donors_count }} don(s) confirmé(s)</span></div>
        @if ($project->collected_amount < (float) $project->target_budget)
        <span class="text-[.78rem] text-muted">Reste à financer : {{ \App\Models\Project::money((float) $project->target_budget - $project->collected_amount) }} DT</span>
        @endif
      </div>

      {{-- B1 : score déterministe et explication rédigée par l'IA (repli : texte gabarit) --}}
      <div class="card p-[18px] flex flex-col gap-3">
        <div class="flex items-center justify-between gap-2"><h2>Priorité</h2><x-status-badge :value="$project->ai_priority_score !== null ? $project->ai_priority_score.'/100' : 'Non évalué'" :variant="$priorityBadge" /></div>
        @if ($project->ai_priority_reason)
        <p class="text-[.88rem]">{{ $project->ai_priority_reason }}</p>
        <span class="text-[.76rem] text-muted">Calculé le {{ $project->ai_scored_at?->format('d/m/Y H:i') }}</span>
        @endif
        <div class="flex flex-col gap-1.5">
          @foreach ($factors as $factor)
          <div class="flex items-center justify-between gap-2 text-[.82rem]"><span>{{ $factor['label'] }}</span><span class="font-mono text-muted">{{ round($factor['normalized'] * 100) }} % × {{ $factor['weight'] }}</span></div>
          @endforeach
        </div>
        <form method="POST" action="{{ route('admin.projects.score', $project) }}">
          @csrf
          <button class="btn btn-ghost w-full justify-center" type="submit">Recalculer le score</button>
        </form>
      </div>

      <div class="card p-[18px] flex flex-col gap-3"><h2>Par source</h2>
        <div class="grid grid-fit-[170px] gap-2">
          @foreach (\App\Enums\FundingSource::cases() as $source)
          <div><span class="caption block">{{ $source->label() }}</span><span class="font-mono">{{ \App\Models\Project::money($bySource[$source->value] ?? 0) }} DT</span></div>
          @endforeach
        </div>
      </div>
      @if ($project->infrastructure)
      <div class="card p-[18px] flex flex-col gap-3"><h2>Infrastructure</h2>
        <div class="grid grid-fit-[170px] gap-2">
          <div><span class="caption block">Code</span><span class="font-mono">{{ $project->infrastructure->code }}</span></div>
          <div><span class="caption block">Type</span><span>{{ $project->infrastructure->type->label() }}</span></div>
          <div><span class="caption block">État</span><x-status-badge :value="$project->infrastructure->status->label()" :variant="$project->infrastructure->status->badge()" dot /></div>
        </div>
        <a class="btn btn-ghost justify-center mt-1.5" href="{{ route('admin.infrastructures.show', $project->infrastructure) }}">Voir l'infrastructure</a>
      </div>
      @endif
    </aside>
  </div>
</section>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
// Cumul des dons confirmés (ligne pleine) contre la progression idéale jusqu'à la date limite (pointillés)
(() => {
  const canvas = document.getElementById('fundingChart');
  if (!canvas || !window.Chart) return;
  const s = JSON.parse(canvas.dataset.series);
  new Chart(canvas, {
    type: 'line',
    data: {labels: s.labels, datasets: [
      {label: 'Dons confirmés (DT)', data: s.cumulative, borderColor: '#1CA7EC', backgroundColor: 'rgba(28,167,236,.12)', fill: true, tension: .25, spanGaps: false, pointRadius: 0},
      {label: 'Rythme idéal (DT)', data: s.ideal, borderColor: '#8AA4B8', borderDash: [6, 4], pointRadius: 0, fill: false},
    ]},
    options: {maintainAspectRatio: false, interaction: {mode: 'index', intersect: false}, scales: {y: {beginAtZero: true, suggestedMax: s.target}}},
  });
})();
</script>
@endpush
