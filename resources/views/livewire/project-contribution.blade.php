{{-- Composant Livewire ProjectContribution : sans JavaScript, le formulaire est envoyé à la route POST projects.fundings.store --}}
<div class="card flex flex-col gap-3.5 p-[22px]">
  <div><strong class="font-display text-[1.8rem] tabular-nums">{{ \App\Models\Project::money($project->collected_amount) }} TND</strong><div class="text-muted">collectés sur {{ \App\Models\Project::money($project->target_budget) }} TND</div></div>
  <div class="progress-track"><span class="progress-bar transition-[width] duration-500" style="width:{{ $project->progress_percent }}%"></span></div>
  <div class="flex flex-wrap items-center justify-between gap-3 text-[.88rem]"><span><strong>{{ $project->progress_percent }} %</strong> financé</span>@if ($project->isExpired())<span>Collecte close</span>@else<span><strong>{{ $project->days_left }}</strong> jour{{ $project->days_left > 1 ? 's' : '' }} restant{{ $project->days_left > 1 ? 's' : '' }}</span>@endif</div>

  @if ($thanks)
  <div class="callout bg-ok-soft text-ok-fg" role="status">✓ {{ $thanks }}</div>
  @endif

  @if ($project->acceptsContributions())
    @auth
    @unless (auth()->user()->hasVerifiedEmail())
    {{-- Adresse e-mail non vérifiée : contribution impossible (middleware « verified ») --}}
    <a class="btn btn-primary justify-center" href="{{ route('verification.notice') }}">Vérifiez votre adresse e-mail pour contribuer</a>
    @else
    <form class="flex flex-col gap-3.5" method="POST" action="{{ route('projects.fundings.store', $project) }}" wire:submit="contribute" novalidate>
      @csrf
      <div @class(['flex flex-col gap-1.5', '[&_.input]:border-crit [&_.input]:bg-crit-soft' => $errors->hasAny(['amount', 'project'])])>
        <label class="text-[.88rem] font-semibold" for="amount">Montant de votre contribution</label>
        <div class="grid grid-cols-3 gap-2">
          @foreach ($presets as $preset)
          <input class="sr-only" type="radio" name="amount" id="a{{ $loop->iteration }}" value="{{ $preset }}" wire:model.live="amount"><label @class(['choice-tile', 'opacity-60' => filled($amountOther)]) for="a{{ $loop->iteration }}">{{ $preset }} TND</label>
          @endforeach
        </div>
        <input class="input" id="amount" name="amount_other" wire:model.live.debounce.400ms="amountOther" inputmode="decimal" placeholder="Autre montant (TND)">
        <span class="text-[.8rem] text-muted">De {{ \App\Models\Funding::MIN_CONTRIBUTION }} à {{ \App\Models\Project::money(\App\Models\Funding::MAX_CONTRIBUTION) }} TND.</span>
        @error('amount')<span class="flex items-center gap-1.5 text-[.8rem] font-medium text-crit">{{ $message }}</span>@enderror
        @error('project')<span class="flex items-center gap-1.5 text-[.8rem] font-medium text-crit">{{ $message }}</span>@enderror
      </div>
      <button class="btn btn-primary justify-center" type="submit" wire:loading.attr="disabled" wire:target="contribute" @disabled($errors->has('amount'))>
        <span wire:loading.remove wire:target="contribute">Contribuer au projet</span>
        <span wire:loading wire:target="contribute">Enregistrement…</span>
      </button>
    </form>
    @endunless
    <p class="text-[.8rem] text-muted">Paiement simulé : aucun montant n'est réellement débité.</p>
    @else
    <a class="btn btn-primary justify-center" href="{{ route('login') }}">Connectez-vous pour contribuer</a>
    <p class="text-[.8rem] text-muted">Paiement simulé : aucun montant n'est réellement débité.</p>
    @endauth
  @else
  <p class="text-[.88rem] text-muted">Ce projet n'accepte plus de contributions : {{ $project->isExpired() && $project->status === \App\Enums\ProjectStatus::EnCollecte ? 'la collecte est terminée' : 'il est « '.$project->status->label().' »' }}.</p>
  @endif
</div>
