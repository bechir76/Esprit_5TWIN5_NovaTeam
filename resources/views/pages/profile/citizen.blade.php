@extends('layouts.layout')

@section('title', 'Mon profil')

@section('content')
<section class="pt-10 pb-16" data-page="profil">
  <div class="mx-auto max-w-[1200px] px-5">
    <div class="mb-7 flex flex-col gap-2">
      <span class="kicker">Espace citoyen</span>
      <h2>Mon profil</h2>
    </div>

    <div class="grid items-start gap-6 min-[901px]:grid-cols-[260px_1fr]">
      <aside class="card flex flex-col gap-4 p-[22px] min-[901px]:sticky min-[901px]:top-[90px]">
        <div class="flex flex-col items-center gap-2 text-center">
          <div class="grid size-[84px] place-items-center rounded-full bg-teal font-display text-[1.8rem] font-extrabold text-[#06302C]">{{ $user->initials() }}</div>
          <strong>{{ $user->name }}</strong>
          <span class="text-[.85rem] text-muted">Membre depuis {{ $user->created_at?->translatedFormat('F Y') }}</span>
        </div>
        <nav class="flex flex-col gap-0.5">
          @foreach (['informations' => 'Informations personnelles', 'mot-de-passe' => 'Mot de passe', 'signalements' => 'Mes signalements', 'contributions' => 'Mes contributions', 'notifications' => 'Notifications'] as $anchor => $label)
          <a @class(['flex gap-2.5 rounded-lg px-3.5 py-[11px] font-medium', 'bg-water-soft text-ink' => $loop->first, 'text-muted hover:bg-surface-2 hover:text-ink' => ! $loop->first])
             href="{{ route('profile.edit') }}#{{ $anchor }}">{{ $label }}</a>
          @endforeach
        </nav>
      </aside>

      <div class="flex flex-col gap-6">
        <form class="card flex scroll-mt-24 flex-col gap-4 p-[22px]" id="informations" method="POST" action="{{ route('profile.update') }}" novalidate>
          @csrf
          @method('PUT')
          <h3>Informations personnelles</h3>
          <div class="grid gap-3.5 sm:grid-cols-2">
            <x-form.input name="name" label="Nom complet" :value="$user->name" autocomplete="name" />
            <x-form.input name="email" label="E-mail" type="email" :value="$user->email" autocomplete="email" />
            <x-form.input name="phone" label="Téléphone" type="tel" :value="$user->phone" autocomplete="tel" />
            <x-form.input name="address" label="Adresse" :value="$user->address" autocomplete="street-address" />
          </div>
          <div class="flex justify-end"><button class="btn btn-primary" type="submit">Enregistrer</button></div>
        </form>

        <form class="card flex scroll-mt-24 flex-col gap-4 p-[22px]" id="mot-de-passe" method="POST" action="{{ route('password.update') }}" novalidate>
          @csrf
          @method('PUT')
          <h3>Changer le mot de passe</h3>
          <div class="grid gap-3.5 md:grid-cols-3">
            <x-form.input name="current_password" label="Mot de passe actuel" type="password" autocomplete="current-password" />
            <x-form.input name="password" label="Nouveau" type="password" autocomplete="new-password" />
            <x-form.input name="password_confirmation" label="Confirmation" type="password" autocomplete="new-password" />
          </div>
          <div class="flex justify-end"><button class="btn btn-navy" type="submit">Mettre à jour</button></div>
        </form>

        <div class="card scroll-mt-24 p-[22px]" id="signalements">
          <div class="mb-2 flex flex-wrap items-center justify-between gap-3"><h3>Mes signalements</h3><span class="font-mono text-[.85rem] text-muted">{{ $reports->count() }} au total</span></div>
          @forelse ($reports as $incident)
          <div class="flex items-start gap-3.5 border-b border-line py-4 last:border-b-0">
            <div class="grid size-10 flex-none place-items-center rounded-card" style="background:{{ $incident->type->softColor() }}" title="{{ $incident->type->label() }}">{{ $incident->type->icon() }}</div>
            <div class="min-w-0 flex-1"><strong>{{ $incident->title }}</strong><div class="font-mono text-[.8rem] text-muted">{{ $incident->reference }} · {{ $incident->type->label() }} · {{ $incident->created_at->format('d/m/Y') }}</div></div>
            <span class="badge {{ $incident->severity->badge() }}">{{ $incident->severity->label() }}</span>
            <span class="badge {{ $incident->status->badge() }}">{{ $incident->status->label() }}</span>
          </div>
          @empty
          <p class="py-4 text-muted">Vous n'avez encore signalé aucun incident. <a class="text-water underline" href="{{ route('incidents.create') }}">Signaler un incident</a></p>
          @endforelse
        </div>

        <div class="grid gap-5 lg:grid-cols-2">
          <div class="card scroll-mt-24 p-[22px]" id="contributions">
            <h3 class="mb-2.5">Mes contributions</h3>
            @forelse ($contributions as $funding)
            <div class="line-item">
              <span>
                @if ($funding->project->is_public && $funding->project->status !== \App\Enums\ProjectStatus::Brouillon)
                <a class="hover:underline" href="{{ route('projects.show', $funding->project) }}">{{ $funding->project->title }}</a>
                @else
                {{ $funding->project->title }}
                @endif
                <br><span class="font-mono text-[.76rem] text-muted">{{ $funding->reference }} · {{ $funding->funded_at->format('d/m/Y') }}</span>
              </span>
              <span class="font-mono whitespace-nowrap">{{ \App\Models\Project::money($funding->amount) }} TND</span>
            </div>
            @empty
            <p class="py-2 text-muted">Vous n'avez encore soutenu aucun projet. <a class="text-water underline" href="{{ route('projects.index') }}">Voir les projets</a></p>
            @endforelse
            <div class="line-item border-b-0 font-bold"><span>Total</span><span class="font-mono">{{ \App\Models\Project::money($contributions->sum('amount')) }} TND</span></div>
          </div>

          {{-- Préférences simulées : pas encore enregistrées --}}
          <div class="card scroll-mt-24 p-[22px]" id="notifications">
            <h3 class="mb-1">Notifications</h3>
            @foreach ([['Changement de statut de mes signalements', 'Statut', true], ['Coupures programmées dans ma commune', 'Coupures', true], ['Avancement des projets soutenus', 'Projets', false]] as [$label, $aria, $checked])
            <div @class(['flex items-center justify-between gap-4 py-3.5', 'border-b border-line' => ! $loop->last])>
              <span>{{ $label }}</span>
              <label class="relative inline-block h-[22px] w-10 flex-none"><input class="peer sr-only" type="checkbox" @checked($checked) aria-label="{{ $aria }}"><span class="switch-track"></span></label>
            </div>
            @endforeach
          </div>
        </div>

        @unless ($user->isStaff())
        <form class="card flex flex-col gap-4 border-crit/40 p-[22px]" method="POST" action="{{ route('profile.destroy') }}" novalidate>
          @csrf
          @method('DELETE')
          <div>
            <h3>Supprimer mon compte</h3>
            <p class="mt-1 text-[.9rem] text-muted">Vos signalements et contributions sont conservés de façon anonyme. Saisissez votre mot de passe pour confirmer.</p>
          </div>
          <div class="flex flex-wrap items-end gap-3">
            <div class="flex min-w-[220px] flex-1 flex-col gap-1.5">
              <label class="text-[.86rem] font-semibold" for="delete_password">Mot de passe</label>
              <input @class(['input', 'border-crit! bg-crit-soft!' => $errors->userDeletion->has('password')]) type="password" id="delete_password" name="password" autocomplete="current-password">
            </div>
            <button class="btn bg-crit text-white" type="submit">Supprimer définitivement</button>
          </div>
          @if ($errors->userDeletion->has('password'))<span class="text-[.8rem] font-medium text-crit-fg">{{ $errors->userDeletion->first('password') }}</span>@endif
        </form>
        @endunless
      </div>
    </div>
  </div>
</section>
@endsection
