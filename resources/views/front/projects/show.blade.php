@extends('layouts.front.app')

@section('title', $project->title)

@php
    // Étapes du projet (statuts dans l'ordre) : terminées avant le statut actuel, « now » pour le statut actuel
    $current = array_search($project->status, $statuses, true);
    $steps = [
        'brouillon' => ['Préparation du projet', 'Diagnostic et chiffrage des travaux'],
        'en_collecte' => ['Collecte des financements', 'Du '.$project->start_date->translatedFormat('j F Y').' au '.$project->end_date->translatedFormat('j F Y')],
        'finance' => ['Objectif de financement atteint', \App\Models\Project::money($project->target_budget).' TND réunis'],
        'en_travaux' => ['Travaux de réhabilitation', 'Réalisés par la régie et ses prestataires'],
        'termine' => ['Remise en service et contrôle qualité', 'Dépenses publiées à la clôture'],
    ];
    $citizen = $bySource[\App\Enums\FundingSource::Citoyen->value] ?? null;
@endphp

@section('content')
<section class="screen" data-page="projet">
  <div class="wrap">
    <a href="{{ route('projects.index') }}" class="muted" style="font-size:.9rem;text-decoration:none">← Retour aux projets</a>
    <div class="screen-head" style="margin-top:14px"><div class="stack" style="gap:10px"><div class="row" style="gap:8px"><span class="badge {{ $project->category->badge() }}">{{ $project->category->label() }}</span><span class="badge {{ $project->status->badge() }}">{{ $project->status->label() }}</span></div><h2>{{ $project->title }}</h2><p class="muted">{{ $project->description }}</p></div></div>
    <div class="detail-layout">
      <div class="stack" style="gap:24px">
        <div class="proj-img card" style="background:{{ $project->imageUrl() ? "url('{$project->imageUrl()}') center/cover" : $project->category->gradient() }};border:0;min-height:260px"></div>
        <div class="card pad">
          <h3 style="margin-bottom:12px">Le projet</h3>
          <div class="budget-row"><span>Ouvrage concerné</span><span class="mono">{{ $project->infrastructure ? $project->infrastructure->code.' · '.$project->infrastructure->name : 'Plusieurs sites' }}</span></div>
          <div class="budget-row"><span>Zone</span><span>{{ $project->infrastructure?->zone->name ?? '—' }}</span></div>
          <div class="budget-row"><span>Début de la collecte</span><span class="mono">{{ $project->start_date->format('d/m/Y') }}</span></div>
          <div class="budget-row" style="border:0"><span>Fin de la collecte</span><span class="mono">{{ $project->end_date->format('d/m/Y') }}</span></div>
        </div>
        <div class="card pad">
          <h3 style="margin-bottom:12px">Budget et financements</h3>
          @foreach ($sources as $source)
            @if ($bySource->has($source->value))
            <div class="budget-row"><span>{{ $source->label() }}@if ($source === \App\Enums\FundingSource::Citoyen) ({{ $citizen->contributions }})@endif</span><span class="mono">{{ \App\Models\Project::money($bySource[$source->value]->total) }} TND</span></div>
            @endif
          @endforeach
          @if ($bySource->isEmpty())
          <div class="budget-row"><span class="muted">Aucun financement pour le moment</span><span class="mono">0 TND</span></div>
          @endif
          <div class="budget-row"><span>Total collecté</span><span class="mono">{{ \App\Models\Project::money($project->collected_amount) }} TND</span></div>
          <div class="budget-row" style="border:0;font-weight:700"><span>Budget objectif</span><span class="mono">{{ \App\Models\Project::money($project->target_budget) }} TND</span></div>
        </div>
        <div class="card pad">
          <h3 style="margin-bottom:16px">Avancement du projet</h3>
          <div class="timeline">
            @foreach ($statuses as $i => $status)
            <div @class(['tl', 'done' => $i < $current || $project->status === \App\Enums\ProjectStatus::Termine, 'now' => $i === $current && $project->status !== \App\Enums\ProjectStatus::Termine]) @if ($loop->last) style="padding-bottom:0" @endif><strong>{{ $steps[$status->value][0] }}</strong><div class="muted" style="font-size:.88rem">{{ $steps[$status->value][1] }}</div></div>
            @endforeach
          </div>
        </div>
      </div>
      <aside class="stack" style="gap:20px;position:sticky;top:90px">
        <div class="card pad stack" style="gap:14px">
          <div><strong class="mono" style="font-size:1.8rem;font-family:var(--f-display)">{{ \App\Models\Project::money($project->collected_amount) }} TND</strong><div class="muted">collectés sur {{ \App\Models\Project::money($project->target_budget) }} TND</div></div>
          <div class="progress"><span style="width:{{ $project->progress_percent }}%"></span></div>
          <div class="row" style="justify-content:space-between;font-size:.88rem"><span><strong>{{ $project->progress_percent }} %</strong> financé</span>@if ($project->isExpired())<span>Collecte close</span>@else<span><strong>{{ $project->days_left }}</strong> jour{{ $project->days_left > 1 ? 's' : '' }} restant{{ $project->days_left > 1 ? 's' : '' }}</span>@endif</div>
          @if ($project->acceptsContributions())
            @auth
            <form class="stack" style="gap:14px" method="POST" action="{{ route('projects.fundings.store', $project) }}" novalidate>
              @csrf
              <div @class(['field', 'is-invalid' => $errors->hasAny(['amount', 'project'])])><label for="amount">Montant de votre contribution</label>
                <div class="seg">@foreach ($presets as $preset)<input type="radio" name="amount" id="a{{ $loop->iteration }}" value="{{ $preset }}" @checked((string) old('amount', 50) === (string) $preset)><label for="a{{ $loop->iteration }}">{{ $preset }} TND</label>@endforeach</div>
                <input class="input" id="amount" name="amount_other" value="{{ old('amount_other') }}" inputmode="decimal" placeholder="Autre montant (TND)">
                <span class="hint">De {{ \App\Models\Funding::MIN_CONTRIBUTION }} à {{ \App\Models\Project::money(\App\Models\Funding::MAX_CONTRIBUTION) }} TND.</span>
                @error('amount')<span class="error">{{ $message }}</span>@enderror
                @error('project')<span class="error">{{ $message }}</span>@enderror
              </div>
              <button class="btn btn-primary" style="justify-content:center" type="submit">Contribuer au projet</button>
            </form>
            <p class="muted" style="font-size:.8rem">Paiement simulé : aucun montant n'est réellement débité.</p>
            @else
            <a class="btn btn-primary" style="justify-content:center" href="{{ route('login') }}">Connectez-vous pour contribuer</a>
            <p class="muted" style="font-size:.8rem">Paiement simulé : aucun montant n'est réellement débité.</p>
            @endauth
          @else
          <p class="muted" style="font-size:.88rem">Ce projet n'accepte plus de contributions : {{ $project->isExpired() && $project->status === \App\Enums\ProjectStatus::EnCollecte ? 'la collecte est terminée' : 'il est « '.$project->status->label().' »' }}.</p>
          @endif
        </div>
        <div class="card pad">
          <h3 style="margin-bottom:10px">Derniers financements</h3>
          @forelse ($project->fundings->take(8) as $funding)
          {{-- L'identité des citoyens contributeurs n'est pas publiée --}}
          <div class="budget-row" @if ($loop->last) style="border:0" @endif><span>{{ $funding->source_type === \App\Enums\FundingSource::Citoyen ? 'Contribution citoyenne' : $funding->donor_name }}<br><span class="muted mono" style="font-size:.76rem">{{ $funding->funded_at->format('d/m/Y') }}</span></span><span class="mono">{{ \App\Models\Project::money($funding->amount) }}</span></div>
          @empty
          <p class="muted" style="font-size:.88rem">Soyez le premier à soutenir ce projet.</p>
          @endforelse
        </div>
      </aside>
    </div>
  </div>
</section>
@endsection
