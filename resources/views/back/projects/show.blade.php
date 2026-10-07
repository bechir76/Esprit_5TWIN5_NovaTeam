@extends('layouts.back.app')

@section('title', $project->title)

@php
    $icon = fn (string $paths) => '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">'.$paths.'</svg>';
    $eye = $icon('<path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/>');
    $edit = $icon('<path d="M12 20h9M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z"/>');
    $trash = $icon('<path d="M3 6h18M8 6V4h8v2M19 6l-1 14H6L5 6"/>');
@endphp

@section('content')
<section data-page="projet" class="stack">
  <x-page-header :title="$project->title" :crumb="'Console / Projets / '.$project->title">
    <x-slot:badges><x-status-badge :value="$project->category->label()" :variant="$project->category->badge()" /><x-status-badge :value="$project->status->label()" :variant="$project->status->badge()" /></x-slot:badges>
    <div class="row">
      @if ($project->is_public && $project->status !== \App\Enums\ProjectStatus::Brouillon)
      <a class="btn btn-ghost" href="{{ route('projects.show', $project) }}" target="_blank" rel="noopener">Voir sur le site</a>
      @endif
      <a class="btn btn-ghost" href="{{ route('admin.projects.edit', $project) }}">Modifier</a><button class="btn btn-danger" type="button" data-del="{{ $project->title }}" data-action="{{ route('admin.projects.destroy', $project) }}">Supprimer</button>
    </div>
  </x-page-header>
  <div class="detail">
    <div class="stack" style="gap:18px">
      <div class="card card-b stack" style="gap:16px">
        <div class="kv">
          <div><span class="label">Catégorie</span><span>{{ $project->category->label() }}</span></div>
          <div><span class="label">Infrastructure</span>
            @if ($project->infrastructure)
            <a href="{{ route('admin.infrastructures.show', $project->infrastructure) }}" style="color:var(--info-fg)">{{ $project->infrastructure->code }} · {{ $project->infrastructure->name }}</a>
            @else
            <span>Multi-sites</span>
            @endif
          </div>
          <div><span class="label">Zone</span><span>{{ $project->infrastructure?->zone->name ?? '—' }}</span></div>
          <div><span class="label">Début de la collecte</span><span class="mono">{{ $project->start_date->format('d/m/Y') }}</span></div>
          <div><span class="label">Fin de la collecte</span><span class="mono">{{ $project->end_date->format('d/m/Y') }}</span></div>
          <div><span class="label">Jours restants</span><span class="mono">{{ $project->isExpired() ? 'Échue' : $project->days_left.' j' }}</span></div>
          <div><span class="label">Site public</span><span>{{ $project->is_public ? 'Publié' : 'Non publié' }}</span></div>
          <div><span class="label">Adresse publique</span><span class="mono" style="font-size:.8rem">/projets/{{ $project->slug }}</span></div>
        </div>
        <div><span class="label">Description</span><p style="margin-top:6px;max-width:70ch">{{ $project->description }}</p></div>
        <div><span class="label">Image</span>
          @if ($project->image_path)
          <div class="photos" style="margin-top:8px"><a href="{{ $project->imageUrl() }}" target="_blank" rel="noopener" style="display:block;width:240px;max-width:100%;height:180px;border-radius:8px;overflow:hidden;background:var(--surface-2)"><img src="{{ $project->imageUrl() }}" alt="Image du projet {{ $project->title }}" style="width:100%;height:100%;object-fit:cover"></a></div>
          @else
          <p class="muted" style="margin-top:6px">Aucune image.</p>
          @endif
        </div>
      </div>

      <div class="card">
        <div class="card-h"><h2>Financements</h2><a class="btn btn-primary btn-sm" href="{{ route('admin.fundings.create', ['projet' => $project->id]) }}">+ Ajouter un financement</a></div>
        <div class="table-wrap"><table><thead><tr><th>Date</th><th>Référence</th><th>Source</th><th>Financeur</th><th style="text-align:right">Montant</th><th></th></tr></thead><tbody>
          @forelse ($project->fundings as $funding)
          <tr>
            <td class="mono" style="white-space:nowrap">{{ $funding->funded_at->format('d/m/Y') }}</td>
            <td><a class="mono" href="{{ route('admin.fundings.show', $funding) }}" style="text-decoration:none;font-size:.82rem">{{ $funding->reference }}</a></td>
            <td><x-status-badge :value="$funding->source_type->label()" :variant="$funding->source_type->badge()" /></td>
            <td>{{ $funding->donor_name }}@if ($funding->user)<br><span class="muted" style="font-size:.78rem">{{ $funding->user->email }}</span>@endif</td>
            <td class="mono" style="text-align:right;white-space:nowrap">{{ \App\Models\Project::money($funding->amount) }} TND</td>
            <td><div class="acts">
              <a class="act" href="{{ route('admin.fundings.show', $funding) }}" title="Voir">{!! $eye !!}</a>
              <a class="act" href="{{ route('admin.fundings.edit', $funding) }}" title="Modifier">{!! $edit !!}</a>
              <button class="act del" type="button" data-del="{{ $funding->reference }}" data-action="{{ route('admin.fundings.destroy', ['funding' => $funding, 'retour' => 'projet']) }}" title="Supprimer">{!! $trash !!}</button>
            </div></td>
          </tr>
          @empty
          <tr><td colspan="6" class="muted" style="text-align:center;padding:28px">Aucun financement pour ce projet.</td></tr>
          @endforelse
        </tbody></table></div>
      </div>
    </div>
    <aside class="stack" style="gap:18px">
      <div class="card card-b stack"><h2>Financement</h2>
        <div><strong class="mono" style="font-size:1.5rem">{{ \App\Models\Project::money($project->collected_amount) }} TND</strong><div class="muted" style="font-size:.86rem">collectés sur {{ \App\Models\Project::money($project->target_budget) }} TND</div></div>
        <div class="progress"><span style="width:{{ $project->progress_percent }}%"></span></div>
        <div class="row between" style="font-size:.86rem"><span><strong>{{ $project->progress_percent }} %</strong> financé</span><span class="muted">{{ $project->fundings->count() }} contribution{{ $project->fundings->count() > 1 ? 's' : '' }}</span></div>
        @if ($project->collected_amount < (float) $project->target_budget)
        <span class="hint">Reste à financer : {{ \App\Models\Project::money((float) $project->target_budget - $project->collected_amount) }} TND</span>
        @endif
      </div>
      <div class="card card-b stack"><h2>Par source</h2>
        <div class="kv" style="gap:8px">
          @foreach (\App\Enums\FundingSource::cases() as $source)
          <div><span class="label">{{ $source->label() }}</span><span class="mono">{{ \App\Models\Project::money($bySource[$source->value] ?? 0) }} TND</span></div>
          @endforeach
        </div>
      </div>
      @if ($project->infrastructure)
      <div class="card card-b stack"><h2>Infrastructure</h2>
        <div class="kv" style="gap:8px">
          <div><span class="label">Code</span><span class="mono">{{ $project->infrastructure->code }}</span></div>
          <div><span class="label">Type</span><span>{{ $project->infrastructure->type->label() }}</span></div>
          <div><span class="label">État</span><x-status-badge :value="$project->infrastructure->status->label()" :variant="$project->infrastructure->status->badge()" dot /></div>
        </div>
        <a class="btn btn-ghost" style="justify-content:center;margin-top:6px" href="{{ route('admin.infrastructures.show', $project->infrastructure) }}">Voir l'infrastructure</a>
      </div>
      @endif
    </aside>
  </div>
</section>
@endsection
