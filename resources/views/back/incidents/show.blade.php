@extends('layouts.back.app')

@section('title', $incident->title)

@php
    $icon = fn (string $paths) => '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">'.$paths.'</svg>';
    $eye = $icon('<path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/>');
    $edit = $icon('<path d="M12 20h9M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z"/>');
    $trash = $icon('<path d="M3 6h18M8 6V4h8v2M19 6l-1 14H6L5 6"/>');
@endphp

@section('content')
<section data-page="incident" class="stack">
  <x-page-header :title="$incident->title" :crumb="'Console / Incidents / '.$incident->reference">
    <x-slot:badges><x-status-badge :value="$incident->severity->label()" :variant="$incident->severity->badge()" dot /><x-status-badge :value="$incident->status->label()" :variant="$incident->status->badge()" /></x-slot:badges>
    <div class="row"><a class="btn btn-ghost" href="{{ route('admin.incidents.edit', $incident) }}">Modifier</a><button class="btn btn-danger" type="button" data-del="{{ $incident->reference }}" data-action="{{ route('admin.incidents.destroy', $incident) }}">Supprimer</button></div>
  </x-page-header>
  <div class="detail">
    <div class="stack" style="gap:18px">
      <div class="card card-b stack" style="gap:16px">
        <div class="kv">
          <div><span class="label">Référence</span><span class="mono">{{ $incident->reference }}</span></div>
          <div><span class="label">Type</span><span>{{ $incident->type->icon() }} {{ $incident->type->label() }}</span></div>
          <div><span class="label">Infrastructure liée</span>
            @if ($incident->infrastructure)
            <a href="{{ route('admin.infrastructures.show', $incident->infrastructure) }}" style="color:var(--info-fg)">{{ $incident->infrastructure->name }}</a>
            @else
            <span>Non rattaché</span>
            @endif
          </div>
          <div><span class="label">Zone</span><span>{{ $incident->infrastructure?->zone->name ?? '—' }}</span></div>
          <div><span class="label">Localisation</span><span>{{ $incident->address }}</span></div>
          <div><span class="label">Déclarant</span><span>{{ $incident->user ? $incident->user->name.' · '.mb_strtolower($incident->user->role->label()) : 'Compte supprimé' }}</span></div>
          <div><span class="label">Signalé le</span><span class="mono">{{ $incident->created_at->format('d/m/Y H:i') }}</span></div>
          <div><span class="label">Résolu le</span><span class="mono">{{ $incident->resolved_at?->format('d/m/Y H:i') ?? '—' }}</span></div>
        </div>
        <div><span class="label">Description</span><p style="margin-top:6px;max-width:70ch">{{ $incident->description }}</p></div>
        <div><span class="label">Photo</span>
          @if ($incident->photo_path)
          <div class="photos" style="margin-top:8px"><a href="{{ $incident->photoUrl() }}" target="_blank" rel="noopener" style="display:block;width:240px;max-width:100%;height:180px;border-radius:8px;overflow:hidden;background:var(--surface-2)"><img src="{{ $incident->photoUrl() }}" alt="Photo jointe au signalement {{ $incident->reference }}" style="width:100%;height:100%;object-fit:cover"></a></div>
          @else
          <p class="muted" style="margin-top:6px">Aucune photo jointe.</p>
          @endif
        </div>
      </div>

      <div class="card">
        <div class="card-h"><h2>Interventions</h2>
          @if ($incident->status->isOpen())
          <a class="btn btn-primary btn-sm" href="{{ route('admin.interventions.create', ['incident' => $incident->id]) }}">+ Planifier une intervention</a>
          @endif
        </div>
        <div class="table-wrap"><table><thead><tr><th>Date</th><th>Intervention</th><th>Équipe</th><th>Technicien</th><th>Durée estimée</th><th>Statut</th><th></th></tr></thead><tbody>
          @forelse ($incident->interventions as $intervention)
          <tr>
            <td class="mono" style="white-space:nowrap">{{ $intervention->scheduled_at->format('d/m H:i') }}</td>
            <td><a href="{{ route('admin.interventions.show', $intervention) }}" style="text-decoration:none"><strong>{{ $intervention->title }}</strong></a></td>
            <td>{{ $intervention->team }}</td>
            <td class="muted">{{ $intervention->technician?->name ?? '—' }}</td>
            <td class="mono">{{ $intervention->formattedDuration() }}</td>
            <td><x-status-badge :value="$intervention->status->label()" :variant="$intervention->status->badge()" dot /></td>
            <td><div class="acts">
              <a class="act" href="{{ route('admin.interventions.show', $intervention) }}" title="Voir">{!! $eye !!}</a>
              <a class="act" href="{{ route('admin.interventions.edit', $intervention) }}" title="Modifier">{!! $edit !!}</a>
              <button class="act del" type="button" data-del="{{ $intervention->title }}" data-action="{{ route('admin.interventions.destroy', ['intervention' => $intervention, 'retour' => 'incident']) }}" title="Supprimer">{!! $trash !!}</button>
            </div></td>
          </tr>
          @empty
          <tr><td colspan="7" class="muted" style="text-align:center;padding:28px">Aucune intervention pour cet incident.</td></tr>
          @endforelse
        </tbody></table></div>
      </div>

      <div class="card"><div class="card-h"><h2>Historique</h2></div><div class="card-b timeline">
        <div class="tl done"><strong>Signalement reçu</strong>@if ($incident->user) de {{ $incident->user->name }}@endif<div class="muted">{{ $incident->created_at->format('d/m H:i') }}</div></div>
        @foreach ($incident->interventions as $intervention)
        <div @class(['tl', 'done' => ! $intervention->status->isActive()])><strong>{{ $intervention->title }}</strong> · {{ $intervention->team }} : {{ mb_strtolower($intervention->status->label()) }}<div class="muted">{{ ($intervention->completed_at ?? $intervention->scheduled_at)->format('d/m H:i') }}</div></div>
        @endforeach
        @if ($incident->resolved_at)
        <div class="tl done" style="padding-bottom:0"><strong>Incident résolu</strong><div class="muted">{{ $incident->resolved_at->format('d/m H:i') }}</div></div>
        @else
        <div class="tl" style="padding-bottom:0"><strong>Statut actuel</strong> : {{ $incident->status->label() }}<div class="muted">mis à jour le {{ $incident->updated_at->format('d/m H:i') }}</div></div>
        @endif
      </div></div>
    </div>
    <aside class="stack" style="gap:18px">
      <form class="card card-b stack" method="POST" action="{{ route('admin.incidents.status', $incident) }}" novalidate>
        @csrf
        @method('PATCH')
        <h2>Traitement</h2>
        <x-form.select name="status" label="Statut" :value="$incident->status->value" :options="\App\Enums\IncidentStatus::options()" hint="Le passage à « Résolu » enregistre la date de résolution." />
        <button class="btn btn-primary" type="submit" style="justify-content:center">Mettre à jour</button>
      </form>
      <div class="card card-b stack"><h2>Localisation</h2>
        <x-location-map :latitude="$incident->latitude" :longitude="$incident->longitude" :color="$incident->severity->color()" :height="160" />
        @if ($incident->hasCoordinates())
        <span class="mono muted" style="font-size:.8rem">{{ number_format($incident->latitude, 5, '.', '') }}, {{ number_format($incident->longitude, 5, '.', '') }}</span>
        @endif
      </div>
      @if ($incident->infrastructure)
      <div class="card card-b stack"><h2>Infrastructure</h2>
        <div class="kv" style="gap:8px">
          <div><span class="label">Code</span><span class="mono">{{ $incident->infrastructure->code }}</span></div>
          <div><span class="label">Type</span><span>{{ $incident->infrastructure->type->label() }}</span></div>
          <div><span class="label">État</span><x-status-badge :value="$incident->infrastructure->status->label()" :variant="$incident->infrastructure->status->badge()" dot /></div>
        </div>
        <a class="btn btn-ghost" style="justify-content:center;margin-top:6px" href="{{ route('admin.infrastructures.show', $incident->infrastructure) }}">Voir l'infrastructure</a>
      </div>
      @endif
    </aside>
  </div>
</section>
@endsection
