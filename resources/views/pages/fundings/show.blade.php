@extends('layouts.layout')

@section('title', 'Financement '.$funding->reference)

@section('content')
<section data-page="financement" class="flex flex-col gap-3">
  <x-page-header :title="'Financement '.$funding->reference" :crumb="'Console / Financements / '.$funding->reference">
    <x-slot:badges><x-status-badge :value="$funding->source_type->label()" :variant="$funding->source_type->badge()" /></x-slot:badges>
    <div class="flex flex-wrap items-center gap-2.5"><a class="btn btn-ghost" href="{{ route('admin.fundings.edit', $funding) }}">Modifier</a><button class="btn btn-danger" type="button" data-del="{{ $funding->reference }}" data-action="{{ route('admin.fundings.destroy', $funding) }}">Supprimer</button></div>
  </x-page-header>
  <div class="grid items-start gap-[18px] min-[1101px]:grid-cols-[minmax(0,1fr)_340px]">
    <div class="card p-[18px] flex flex-col gap-4">
      <div class="grid grid-fit-[170px] gap-4">
        <div><span class="caption block">Référence</span><span class="font-mono">{{ $funding->reference }}</span></div>
        <div><span class="caption block">Montant</span><span class="font-mono">{{ \App\Models\Project::money($funding->amount) }} TND</span></div>
        <div><span class="caption block">Date</span><span class="font-mono">{{ $funding->funded_at->format('d/m/Y') }}</span></div>
        <div><span class="caption block">Source</span><span>{{ $funding->source_type->label() }}</span></div>
        <div><span class="caption block">Financeur</span><span>{{ $funding->donor_name }}</span></div>
        <div><span class="caption block">Compte citoyen</span><span>{{ $funding->user ? $funding->user->name.' · '.$funding->user->email : '—' }}</span></div>
        <div><span class="caption block">Enregistré le</span><span class="font-mono">{{ $funding->created_at->format('d/m/Y H:i') }}</span></div>
      </div>
      <div><span class="caption block">Note</span><p class="mt-1.5 max-w-[70ch]">{{ $funding->note ?: '—' }}</p></div>
    </div>
    <aside class="flex flex-col gap-[18px]">
      <div class="card p-[18px] flex flex-col gap-3"><h2>Projet</h2>
        <x-project-progress :project="$funding->project" />
        <div class="grid grid-fit-[170px] gap-2">
          <div><span class="caption block">Statut</span><x-status-badge :value="$funding->project->status->label()" :variant="$funding->project->status->badge()" /></div>
          <div><span class="caption block">Infrastructure</span><span>{{ $funding->project->infrastructure ? $funding->project->infrastructure->code.' · '.$funding->project->infrastructure->zone->name : 'Multi-sites' }}</span></div>
        </div>
        <a class="btn btn-ghost justify-center mt-1.5" href="{{ route('admin.projects.show', $funding->project) }}">Voir le projet</a>
      </div>
    </aside>
  </div>
</section>
@endsection
