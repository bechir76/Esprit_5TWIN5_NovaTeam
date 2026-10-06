@extends('layouts.back.app')

@section('title', $intervention->title)

@section('content')
<section data-page="intervention" class="stack">
  <x-page-header :title="$intervention->title" :crumb="'Console / Interventions / '.$intervention->incident->reference">
    <x-slot:badges><x-status-badge :value="$intervention->status->label()" :variant="$intervention->status->badge()" dot /></x-slot:badges>
    <div class="row"><a class="btn btn-ghost" href="{{ route('admin.interventions.edit', $intervention) }}">Modifier</a><button class="btn btn-danger" type="button" data-del="{{ $intervention->title }}" data-action="{{ route('admin.interventions.destroy', $intervention) }}">Supprimer</button></div>
  </x-page-header>
  <div class="detail">
    <div class="stack" style="gap:18px">
      <div class="card card-b stack" style="gap:16px">
        <div class="kv">
          <div><span class="label">Incident</span><a href="{{ route('admin.incidents.show', $intervention->incident) }}" style="color:var(--info-fg)">{{ $intervention->incident->reference }} · {{ $intervention->incident->title }}</a></div>
          <div><span class="label">Équipe</span><span>{{ $intervention->team }}</span></div>
          <div><span class="label">Technicien</span><span>{{ $intervention->technician?->name ?? 'Non assigné' }}</span></div>
          <div><span class="label">Prévue le</span><span class="mono">{{ $intervention->scheduled_at->format('d/m/Y H:i') }}</span></div>
          <div><span class="label">Durée estimée</span><span class="mono">{{ $intervention->formattedDuration() }}</span></div>
          <div><span class="label">Terminée le</span><span class="mono">{{ $intervention->completed_at?->format('d/m/Y H:i') ?? '—' }}</span></div>
        </div>
        <div><span class="label">Compte rendu</span><p style="margin-top:6px;max-width:70ch">{{ $intervention->report ?: 'Aucun compte rendu pour le moment.' }}</p></div>
      </div>
    </div>
    <aside class="stack" style="gap:18px">
      <div class="card card-b stack"><h2>Incident</h2>
        <div class="kv" style="gap:8px">
          <div><span class="label">Type</span><span>{{ $intervention->incident->type->icon() }} {{ $intervention->incident->type->label() }}</span></div>
          <div><span class="label">Gravité</span><x-status-badge :value="$intervention->incident->severity->label()" :variant="$intervention->incident->severity->badge()" dot /></div>
          <div><span class="label">Statut</span><x-status-badge :value="$intervention->incident->status->label()" :variant="$intervention->incident->status->badge()" /></div>
          <div><span class="label">Localisation</span><span>{{ $intervention->incident->address }}</span></div>
          <div><span class="label">Infrastructure</span><span>{{ $intervention->incident->infrastructure ? $intervention->incident->infrastructure->code.' · '.$intervention->incident->infrastructure->name : '—' }}</span></div>
        </div>
        <a class="btn btn-ghost" style="justify-content:center;margin-top:6px" href="{{ route('admin.incidents.show', $intervention->incident) }}">Voir l'incident</a>
      </div>
    </aside>
  </div>
</section>
@endsection
