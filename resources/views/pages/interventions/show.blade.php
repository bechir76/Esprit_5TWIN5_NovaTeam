@extends('layouts.layout')

@section('title', $intervention->title)

@section('content')
<section data-page="intervention" class="flex flex-col gap-3">
  <x-page-header :title="$intervention->title" :crumb="'Console / Interventions / '.$intervention->incident->reference">
    <x-slot:badges><x-status-badge :value="$intervention->status->label()" :variant="$intervention->status->badge()" dot /></x-slot:badges>
    <div class="flex flex-wrap items-center gap-2.5"><a class="btn btn-ghost" href="{{ route('admin.interventions.edit', $intervention) }}">Modifier</a><button class="btn btn-danger" type="button" data-del="{{ $intervention->title }}" data-action="{{ route('admin.interventions.destroy', $intervention) }}">Supprimer</button></div>
  </x-page-header>
  <div class="grid items-start gap-[18px] min-[1101px]:grid-cols-[minmax(0,1fr)_340px]">
    <div class="flex flex-col gap-[18px]">
      <div class="card p-[18px] flex flex-col gap-4">
        <div class="grid grid-fit-[170px] gap-4">
          <div><span class="caption block">Incident</span><a class="text-info-fg underline" href="{{ route('admin.incidents.show', $intervention->incident) }}">{{ $intervention->incident->reference }} · {{ $intervention->incident->title }}</a></div>
          <div><span class="caption block">Équipe</span><span>{{ $intervention->team }}</span></div>
          <div><span class="caption block">Technicien</span><span>{{ $intervention->technician?->name ?? 'Non assigné' }}</span></div>
          <div><span class="caption block">Prévue le</span><span class="font-mono">{{ $intervention->scheduled_at->format('d/m/Y H:i') }}</span></div>
          <div><span class="caption block">Durée estimée</span><span class="font-mono">{{ $intervention->formattedDuration() }}</span></div>
          <div><span class="caption block">Terminée le</span><span class="font-mono">{{ $intervention->completed_at?->format('d/m/Y H:i') ?? '—' }}</span></div>
        </div>
        <div><span class="caption block">Compte rendu</span><p class="mt-1.5 max-w-[70ch]">{{ $intervention->report ?: 'Aucun compte rendu pour le moment.' }}</p></div>
      </div>
    </div>
    <aside class="flex flex-col gap-[18px]">
      <div class="card p-[18px] flex flex-col gap-3"><h2>Incident</h2>
        <div class="grid grid-fit-[170px] gap-2">
          <div><span class="caption block">Type</span><span>{{ $intervention->incident->type->icon() }} {{ $intervention->incident->type->label() }}</span></div>
          <div><span class="caption block">Gravité</span><x-status-badge :value="$intervention->incident->severity->label()" :variant="$intervention->incident->severity->badge()" dot /></div>
          <div><span class="caption block">Statut</span><x-status-badge :value="$intervention->incident->status->label()" :variant="$intervention->incident->status->badge()" /></div>
          <div><span class="caption block">Localisation</span><span>{{ $intervention->incident->address }}</span></div>
          <div><span class="caption block">Infrastructure</span><span>{{ $intervention->incident->infrastructure ? $intervention->incident->infrastructure->code.' · '.$intervention->incident->infrastructure->name : '—' }}</span></div>
        </div>
        <a class="btn btn-ghost justify-center mt-1.5" href="{{ route('admin.incidents.show', $intervention->incident) }}">Voir l'incident</a>
      </div>
    </aside>
  </div>
</section>
@endsection
