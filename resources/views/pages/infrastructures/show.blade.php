@extends('layouts.layout')

@section('title', $infrastructure->name)

{{-- Fiche 360° d'un ouvrage : indicateurs, onglets (Informations, Incidents et interventions, Projets, Analyses d'eau,
     Historique), score de risque (Livewire InfrastructureRisk) et action « Enregistrer une inspection ». --}}
@use('App\Enums\InfrastructureStatus')
@php
    $statusLabel = fn (?string $value) => InfrastructureStatus::tryFrom((string) $value)?->label() ?? '—';
    $hours = $indicators['average_resolution_hours'];
    $tabs = [
        'infos' => 'Informations',
        'incidents' => 'Incidents liés ('.$infrastructure->incidents->count().')',
        'projects' => 'Projets ('.$infrastructure->projects->count().')',
        'analyses' => 'Analyses d\'eau ('.$infrastructure->waterAnalyses->count().')',
        'history' => 'Historique',
    ];
@endphp

@section('content')
<section data-page="infra" class="flex flex-col gap-3">
  <x-page-header :title="$infrastructure->name" :crumb="'Console / Infrastructures / '.$infrastructure->code">
    <x-slot:badges>
      <x-status-badge :value="$infrastructure->status->label()" :variant="$infrastructure->status->badge()" dot />
      @if ($infrastructure->isInspectionOverdue())<x-status-badge value="Inspection en retard" variant="b-crit" />@endif
      @if ($infrastructure->risk_level)<x-status-badge :value="'Risque '.mb_strtolower($infrastructure->risk_level->label())" :variant="$infrastructure->risk_level->badge()" />@endif
      @unless ($infrastructure->is_public)<x-status-badge value="Non publique" variant="b-mute" />@endunless
    </x-slot:badges>
    <div class="flex flex-wrap items-center gap-2.5">
      @can('downloadPdf', $infrastructure)<a class="btn btn-ghost" href="{{ route('admin.infrastructures.pdf', $infrastructure) }}">Fiche PDF</a>@endcan
      @can('update', $infrastructure)<a class="btn btn-ghost" href="{{ route('admin.infrastructures.edit', $infrastructure) }}">Modifier</a>@endcan
      @can('delete', $infrastructure)<button class="btn btn-danger" type="button" data-del="{{ $infrastructure->name }}" data-action="{{ route('admin.infrastructures.destroy', $infrastructure) }}">Supprimer</button>@endcan
    </div>
  </x-page-header>

  <div class="grid grid-fit-[200px] gap-[18px]">
    <x-kpi-card label="Incidents sur 12 mois" :value="$indicators['incidents_12_months']" :delta="$infrastructure->incidents->filter(fn ($i) => $i->status->isOpen())->count().' ouvert(s)'" color="var(--color-crit)" />
    <x-kpi-card label="Délai moyen de résolution" :value="$hours === null ? '—' : ($hours >= 48 ? number_format($hours / 24, 1, ',', ' ') : number_format($hours, 1, ',', ' '))" :unit="$hours === null ? null : ($hours >= 48 ? 'j' : 'h')" :delta="$infrastructure->incidents->whereNotNull('resolved_at')->count().' incident(s) résolu(s)'" />
    <x-kpi-card label="Investi dans les projets" :value="\App\Models\Project::money(round($indicators['invested']))" unit="DT" :delta="$infrastructure->projects->count().' projet(s) lié(s)'" color="var(--color-teal)" />
    <x-kpi-card label="Conformité des analyses" :value="$indicators['compliance_rate'] ?? '—'" :unit="$indicators['compliance_rate'] === null ? null : '%'" :delta="$infrastructure->waterAnalyses->count().' analyse(s)'" color="var(--color-water)" />
  </div>

  <div class="grid items-start gap-[18px] min-[1101px]:grid-cols-[minmax(0,1fr)_340px]">
    <div class="card" x-data="{ tab: 'infos' }">
      <div class="flex flex-wrap gap-1.5 border-b border-line p-2.5" role="tablist" aria-label="Sections de la fiche">
        @foreach ($tabs as $key => $label)
        <button class="rounded-[8px] px-3 py-1.5 text-[.88rem] font-semibold" type="button" role="tab" x-on:click="tab = '{{ $key }}'" :aria-selected="tab === '{{ $key }}'" :class="tab === '{{ $key }}' ? 'bg-navy text-white' : 'text-muted hover:bg-surface-2'">{{ $label }}</button>
        @endforeach
      </div>

      {{-- Informations --}}
      <div class="p-[18px] flex flex-col gap-4" role="tabpanel" x-show="tab === 'infos'">
        <div class="grid grid-fit-[170px] gap-4">
          <div><span class="caption block">Code ouvrage</span><span class="font-mono">{{ $infrastructure->code }}</span></div>
          <div><span class="caption block">Type</span><span>{{ $infrastructure->type->label() }}</span></div>
          <div><span class="caption block">Zone</span><a class="text-info-fg underline" href="{{ route('admin.zones.show', $infrastructure->zone) }}">{{ $infrastructure->zone->name }}</a></div>
          <div><span class="caption block">Commune</span><span>{{ $infrastructure->zone->municipality }} · {{ $infrastructure->zone->governorate }}</span></div>
          <div><span class="caption block">Capacité</span><span class="font-mono">{{ $infrastructure->formattedCapacity() }}</span></div>
          <div><span class="caption block">Matériau</span><span>{{ $infrastructure->material ?? '—' }}</span></div>
          <div><span class="caption block">Mise en service</span><span class="font-mono">{{ $infrastructure->commissioned_year }} · {{ $infrastructure->age() }} ans</span></div>
          <div><span class="caption block">Pression nominale</span><span class="font-mono">{{ $infrastructure->formattedPressure() ?? '—' }}</span></div>
          <div><span class="caption block">Dernière inspection</span><span class="font-mono">{{ $infrastructure->last_inspection_at?->format('d/m/Y') ?? 'Non renseignée' }}</span></div>
          <div><span class="caption block">Prochaine inspection</span><span @class(['font-mono', 'text-crit-fg font-semibold' => $infrastructure->isInspectionOverdue()])>{{ $infrastructure->next_inspection_at?->format('d/m/Y') ?? '—' }}</span></div>
          <div><span class="caption block">Carte publique</span><span>{{ $infrastructure->is_public ? 'Visible' : 'Masquée' }}</span></div>
        </div>
        <div><span class="caption block">Description</span><p class="mt-1.5 max-w-[70ch]">{{ $infrastructure->description ?: 'Aucune description.' }}</p></div>
      </div>

      {{-- Incidents et leurs interventions --}}
      <div role="tabpanel" x-show="tab === 'incidents'" x-cloak>
        <div class="flex justify-end p-[18px] pb-0"><a class="btn btn-primary btn-sm" href="{{ route('admin.incidents.create', ['infrastructure' => $infrastructure->id]) }}">+ Créer un incident</a></div>
        <div class="overflow-x-auto"><table class="data-table"><thead><tr><th>Incident</th><th>Type</th><th>Gravité</th><th>Statut</th><th>Interventions</th><th>Signalé le</th></tr></thead><tbody>
          @forelse ($infrastructure->incidents as $incident)
          <tr>
            <td><span class="mr-2.5 inline-block h-7 w-1 rounded-[2px] align-middle" style="background:{{ $incident->severity->color() }}"></span><span class="inline-block align-middle"><a class="font-semibold" href="{{ route('admin.incidents.show', $incident) }}">{{ $incident->title }}</a><br><span class="font-mono text-muted text-[.76rem]">{{ $incident->reference }}</span></span></td>
            <td>{{ $incident->type->icon() }} {{ $incident->type->label() }}</td>
            <td><x-status-badge :value="$incident->severity->label()" :variant="$incident->severity->badge()" dot /></td>
            <td><x-status-badge :value="$incident->status->label()" :variant="$incident->status->badge()" /></td>
            <td class="text-[.84rem]">
              @forelse ($incident->interventions as $intervention)
              <a class="underline" href="{{ route('admin.interventions.show', $intervention) }}">{{ $intervention->title }}</a> · {{ $intervention->status->label() }} · <span class="font-mono">{{ $intervention->scheduled_at->format('d/m/Y') }}</span>@unless ($loop->last)<br>@endunless
              @empty
              <span class="text-muted">Aucune</span>
              @endforelse
            </td>
            <td class="font-mono">{{ $incident->created_at->format('d/m/Y') }}</td>
          </tr>
          @empty
          <tr><td colspan="6" class="text-muted text-center p-7">Aucun incident signalé sur cet ouvrage.</td></tr>
          @endforelse
        </tbody></table></div>
      </div>

      {{-- Projets de rénovation --}}
      <div role="tabpanel" x-show="tab === 'projects'" x-cloak>
        <div class="overflow-x-auto"><table class="data-table"><thead><tr><th>Projet</th><th>Statut</th><th>Budget</th><th>Collecté</th><th>Avancement</th></tr></thead><tbody>
          @forelse ($infrastructure->projects as $project)
          <tr>
            <td><a class="font-semibold" href="{{ route('admin.projects.show', $project) }}">{{ $project->title }}</a><br><span class="text-[.76rem] text-muted">{{ $project->category->label() }}</span></td>
            <td><x-status-badge :value="$project->status->label()" :variant="$project->status->badge()" /></td>
            <td class="font-mono">{{ \App\Models\Project::money($project->target_budget) }} DT</td>
            <td class="font-mono">{{ \App\Models\Project::money($project->collected_amount) }} DT</td>
            <td class="min-w-[120px]"><div class="progress-track"><span class="progress-bar" style="width:{{ $project->progress_percent }}%"></span></div><span class="font-mono text-[.78rem] text-muted">{{ $project->progress_percent }} %</span></td>
          </tr>
          @empty
          <tr><td colspan="5" class="text-muted text-center p-7">Aucun projet lié à cet ouvrage.</td></tr>
          @endforelse
        </tbody></table></div>
      </div>

      {{-- Analyses d'eau --}}
      <div role="tabpanel" x-show="tab === 'analyses'" x-cloak>
        <div class="overflow-x-auto"><table class="data-table"><thead><tr><th>Analyse</th><th>Prélevée le</th><th>Laboratoire</th><th>Mesures conformes</th><th>Statut</th></tr></thead><tbody>
          @forelse ($infrastructure->waterAnalyses as $analysis)
          <tr>
            <td><a class="font-mono font-semibold" href="{{ route('admin.water-analyses.show', $analysis) }}">{{ $analysis->reference }}</a></td>
            <td class="font-mono">{{ $analysis->sampled_at->format('d/m/Y H:i') }}</td>
            <td>{{ $analysis->laboratory ?? '—' }}</td>
            <td class="font-mono">{{ $analysis->compliance_rate === null ? '—' : $analysis->compliance_rate.' %' }}</td>
            <td><x-status-badge :value="$analysis->status->label()" :variant="$analysis->status->badge()" /></td>
          </tr>
          @empty
          <tr><td colspan="5" class="text-muted text-center p-7">Aucune analyse d'eau sur cet ouvrage.</td></tr>
          @endforelse
        </tbody></table></div>
      </div>

      {{-- Historique (spatie/laravel-activitylog) : changements d'état, inspections et recalculs du risque --}}
      <div class="p-[18px]" role="tabpanel" x-show="tab === 'history'" x-cloak>
        <div class="timeline">
          @forelse ($activities as $activity)
          @php
              $old = $activity->properties['old'] ?? [];
              $new = $activity->properties['attributes'] ?? [];
          @endphp
          <div @class(['timeline-item', 'is-done' => ! $loop->first, 'is-now' => $loop->first, 'pb-0' => $loop->last])>
            @switch ($activity->event)
              @case('inspected')
                <strong>Inspection du {{ \Illuminate\Support\Carbon::parse($activity->properties['date'])->format('d/m/Y') }}</strong> : état « {{ $statusLabel($old['status'] ?? null) }} » → « {{ $statusLabel($new['status'] ?? null) }} »
                @if (! empty($activity->properties['note']))<div class="text-[.86rem]">{{ $activity->properties['note'] }}</div>@endif
                @break
              @case('risk_computed')
                <strong>Score de risque recalculé</strong> : {{ $old['risk_score'] ?? '—' }} → {{ $new['risk_score'] ?? '—' }}/100 ({{ \App\Enums\RiskLevel::tryFrom((string) ($new['risk_level'] ?? ''))?->label() ?? '—' }}, {{ ($activity->properties['source'] ?? '') === 'ia' ? 'texte IA' : 'texte par règles' }})
                @break
              @case('created')
                <strong>Fiche créée</strong> : état « {{ $statusLabel($new['status'] ?? null) }} »
                @break
              @default
                <strong>Fiche modifiée</strong>@if (array_key_exists('status', $new)) : état « {{ $statusLabel($old['status'] ?? null) }} » → « {{ $statusLabel($new['status']) }} »@else : {{ collect(array_keys($new))->implode(', ') }}@endif
            @endswitch
            <div class="text-muted text-[.84rem]">{{ $activity->created_at->format('d/m/Y H:i') }} · {{ $activity->causer?->name ?? 'Système' }}</div>
          </div>
          @empty
          <p class="text-muted">Aucun événement enregistré.</p>
          @endforelse
        </div>
      </div>
    </div>

    <aside class="flex flex-col gap-[18px]">
      <livewire:infrastructure-risk :infrastructure="$infrastructure" />

      {{-- A1 : plan d'inspections --}}
      <div class="card p-[18px] flex flex-col gap-3">
        <div class="flex items-center justify-between gap-2"><h2>Inspection</h2>@if ($infrastructure->isInspectionOverdue())<x-status-badge value="Inspection en retard" variant="b-crit" />@endif</div>
        <div class="line-item"><span class="text-muted">Prochaine inspection</span><span class="font-mono">{{ $infrastructure->next_inspection_at?->format('d/m/Y') ?? '—' }}</span></div>
        @if ($infrastructure->isInspectionOverdue())
        <div class="line-item"><span class="text-muted">Retard</span><span class="font-mono text-crit-fg">{{ $infrastructure->inspectionDaysOverdue() }} j</span></div>
        @endif
        <div class="line-item"><span class="text-muted">Fréquence</span><span>tous les {{ $infrastructure->inspectionIntervalMonths() }} mois</span></div>
        @can('inspect', $infrastructure)
        <form class="flex flex-col gap-3 border-t border-line pt-3" method="POST" action="{{ route('admin.infrastructures.inspect', $infrastructure) }}">
          @csrf
          <strong class="text-[.92rem]">Enregistrer une inspection</strong>
          <x-form.input name="inspected_at" label="Date" type="date" :value="now()->format('Y-m-d')" max="{{ now()->format('Y-m-d') }}" required />
          <x-form.select name="status" label="État constaté" :options="InfrastructureStatus::options()" :value="$infrastructure->status->value" required />
          <x-form.textarea name="note" label="Note" rows="3" placeholder="Constats, réserves, travaux à prévoir…" />
          <button class="btn btn-primary" type="submit">Enregistrer l'inspection</button>
        </form>
        @endcan
      </div>

      <div class="card p-[18px] flex flex-col gap-3"><h2>Localisation</h2>
        <x-location-map :latitude="$infrastructure->latitude" :longitude="$infrastructure->longitude" :color="$infrastructure->status->color()" :height="180" />
        @if ($infrastructure->hasCoordinates())
        <span class="font-mono text-muted text-[.8rem]">{{ number_format($infrastructure->latitude, 5, '.', '') }}, {{ number_format($infrastructure->longitude, 5, '.', '') }}</span>
        @endif
      </div>
    </aside>
  </div>
</section>
@endsection
