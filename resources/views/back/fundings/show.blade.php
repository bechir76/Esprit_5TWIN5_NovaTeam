@extends('layouts.back.app')

@section('title', 'Financement '.$funding->reference)

@section('content')
<section data-page="financement" class="stack">
  <x-page-header :title="'Financement '.$funding->reference" :crumb="'Console / Financements / '.$funding->reference">
    <x-slot:badges><x-status-badge :value="$funding->source_type->label()" :variant="$funding->source_type->badge()" /></x-slot:badges>
    <div class="row"><a class="btn btn-ghost" href="{{ route('admin.fundings.edit', $funding) }}">Modifier</a><button class="btn btn-danger" type="button" data-del="{{ $funding->reference }}" data-action="{{ route('admin.fundings.destroy', $funding) }}">Supprimer</button></div>
  </x-page-header>
  <div class="detail">
    <div class="card card-b stack" style="gap:16px">
      <div class="kv">
        <div><span class="label">Référence</span><span class="mono">{{ $funding->reference }}</span></div>
        <div><span class="label">Montant</span><span class="mono">{{ \App\Models\Project::money($funding->amount) }} TND</span></div>
        <div><span class="label">Date</span><span class="mono">{{ $funding->funded_at->format('d/m/Y') }}</span></div>
        <div><span class="label">Source</span><span>{{ $funding->source_type->label() }}</span></div>
        <div><span class="label">Financeur</span><span>{{ $funding->donor_name }}</span></div>
        <div><span class="label">Compte citoyen</span><span>{{ $funding->user ? $funding->user->name.' · '.$funding->user->email : '—' }}</span></div>
        <div><span class="label">Enregistré le</span><span class="mono">{{ $funding->created_at->format('d/m/Y H:i') }}</span></div>
      </div>
      <div><span class="label">Note</span><p style="margin-top:6px;max-width:70ch">{{ $funding->note ?: '—' }}</p></div>
    </div>
    <aside class="stack" style="gap:18px">
      <div class="card card-b stack"><h2>Projet</h2>
        <x-project-progress :project="$funding->project" />
        <div class="kv" style="gap:8px">
          <div><span class="label">Statut</span><x-status-badge :value="$funding->project->status->label()" :variant="$funding->project->status->badge()" /></div>
          <div><span class="label">Infrastructure</span><span>{{ $funding->project->infrastructure ? $funding->project->infrastructure->code.' · '.$funding->project->infrastructure->zone->name : 'Multi-sites' }}</span></div>
        </div>
        <a class="btn btn-ghost" style="justify-content:center;margin-top:6px" href="{{ route('admin.projects.show', $funding->project) }}">Voir le projet</a>
      </div>
    </aside>
  </div>
</section>
@endsection
