{{-- Composant Livewire ProjectContribution : validation en direct puis redirection vers Stripe Checkout ;
     sans JavaScript, le formulaire est envoyé à la route POST projects.donate --}}
<div class="card flex flex-col gap-3.5 p-[22px]">
  <h3>Soutenir ce projet</h3>

  @if ($project->acceptsContributions())
    @auth
    @unless (auth()->user()->hasVerifiedEmail())
    {{-- Adresse e-mail non vérifiée : don impossible (middleware « verified ») --}}
    <a class="btn btn-primary justify-center" href="{{ route('verification.notice') }}">Vérifiez votre adresse e-mail pour contribuer</a>
    @else
    <form class="flex flex-col gap-3.5" method="POST" action="{{ route('projects.donate', $project) }}" wire:submit="contribute" novalidate>
      @csrf
      <div @class(['flex flex-col gap-1.5', '[&_.input]:border-crit [&_.input]:bg-crit-soft' => $errors->hasAny(['amount', 'project'])])>
        <label class="text-[.88rem] font-semibold" for="amount">Montant de votre don</label>
        <div class="grid grid-cols-3 gap-2">
          @foreach ($presets as $preset)
          <input class="sr-only" type="radio" name="amount" id="a{{ $loop->iteration }}" value="{{ $preset }}" wire:model.live="amount"><label @class(['choice-tile', 'opacity-60' => filled($amountOther)]) for="a{{ $loop->iteration }}">{{ $preset }} DT</label>
          @endforeach
        </div>
        <input class="input" id="amount" name="amount_other" wire:model.live.debounce.400ms="amountOther" inputmode="decimal" placeholder="Autre montant (DT)">
        <span class="text-[.8rem] text-muted">De {{ \App\Models\Funding::MIN_CONTRIBUTION }} à {{ \App\Models\Project::money(\App\Models\Funding::MAX_CONTRIBUTION) }} DT.</span>
        @error('amount')<span class="flex items-center gap-1.5 text-[.8rem] font-medium text-crit">{{ $message }}</span>@enderror
        @error('project')<span class="flex items-center gap-1.5 text-[.8rem] font-medium text-crit">{{ $message }}</span>@enderror
      </div>
      <label class="flex items-center gap-2 text-[.86rem]"><input type="checkbox" name="anonymous" value="1" wire:model="anonymous"> Afficher mon don comme « Anonyme »</label>
      <button class="btn btn-primary justify-center" type="submit" wire:loading.attr="disabled" wire:target="contribute" @disabled($errors->has('amount'))>
        <span wire:loading.remove wire:target="contribute">Donner par carte</span>
        <span wire:loading wire:target="contribute">Redirection vers le paiement…</span>
      </button>
    </form>
    @endunless
    <p class="text-[.8rem] text-muted">Paiement sécurisé par Stripe (mode test : carte 4242 4242 4242 4242). Aucune donnée de carte n'est conservée par AquaSecure. Si l'objectif n'est pas atteint à la date limite, votre don est remboursé.</p>
    @else
    <a class="btn btn-primary justify-center" href="{{ route('login') }}">Connectez-vous pour contribuer</a>
    <p class="text-[.8rem] text-muted">Paiement sécurisé par Stripe. Si l'objectif n'est pas atteint à la date limite, les dons sont remboursés.</p>
    @endauth
  @else
  <p class="text-[.88rem] text-muted">Ce projet n'accepte plus de dons : {{ $project->isExpired() && $project->status === \App\Enums\ProjectStatus::Open ? 'la collecte est terminée' : 'il est « '.$project->status->label().' »' }}.</p>
  @endif
</div>
