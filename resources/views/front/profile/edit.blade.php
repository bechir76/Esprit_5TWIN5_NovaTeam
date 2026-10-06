@extends('layouts.front.app')

@section('title', 'Mon profil')

@section('content')
<section class="screen" data-page="profil">
  <div class="wrap">
    <div class="screen-head"><div class="stack" style="gap:8px"><span class="eyebrow">Espace citoyen</span><h2>Mon profil</h2></div></div>
    <div class="profile-layout">
      <aside class="card pad stack" style="gap:16px">
        <div class="stack" style="align-items:center;text-align:center;gap:8px"><div class="big-avatar">{{ $user->initials() }}</div><strong>{{ $user->name }}</strong><span class="muted" style="font-size:.85rem">Membre depuis {{ $user->created_at?->translatedFormat('F Y') }}</span></div>
        <nav class="side-menu stack" style="gap:2px">
          <a href="{{ route('profile.edit') }}" class="on">Informations personnelles</a><a href="{{ route('profile.edit') }}">Mot de passe</a><a href="{{ route('profile.edit') }}">Mes signalements</a><a href="{{ route('profile.edit') }}">Mes contributions</a><a href="{{ route('profile.edit') }}">Notifications</a>
        </nav>
      </aside>
      <div class="stack" style="gap:24px">
        <form class="card pad stack" style="gap:16px" method="POST" action="{{ route('profile.update') }}" novalidate>
          @csrf
          @method('PUT')
          <div class="row" style="justify-content:space-between"><h3>Informations personnelles</h3><button class="btn btn-ghost btn-sm" type="button">Changer la photo</button></div>
          <div class="grid g2" style="gap:14px">
            <x-form.input name="name" label="Nom complet" :value="$user->name" autocomplete="name" />
            <x-form.input name="email" label="E-mail" type="email" :value="$user->email" autocomplete="email" />
            <x-form.input name="phone" label="Téléphone" type="tel" :value="$user->phone" />
            <x-form.input name="address" label="Adresse" :value="$user->address" />
          </div>
          <div class="row" style="justify-content:flex-end"><button class="btn btn-primary" type="submit">Enregistrer</button></div>
        </form>
        <form class="card pad stack" style="gap:16px" method="POST" action="{{ route('password.update') }}" novalidate>
          @csrf
          @method('PUT')
          <h3>Changer le mot de passe</h3>
          <div class="grid g3" style="gap:14px">
            <x-form.input name="current_password" label="Mot de passe actuel" type="password" autocomplete="current-password" />
            <x-form.input name="password" label="Nouveau" type="password" autocomplete="new-password" />
            <x-form.input name="password_confirmation" label="Confirmation" type="password" autocomplete="new-password" />
          </div>
          <div class="row" style="justify-content:flex-end"><button class="btn btn-navy" type="submit">Mettre à jour</button></div>
        </form>
        <div class="card pad">
          <div class="row" style="justify-content:space-between;margin-bottom:8px"><h3>Mes signalements</h3><span class="muted mono" style="font-size:.85rem">{{ $reports->count() }} au total</span></div>
          <div>
            @forelse ($reports as $incident)
            <div class="inc-item"><div class="inc-ic" style="background:{{ $incident->type->softColor() }}" title="{{ $incident->type->label() }}">{{ $incident->type->icon() }}</div><div style="flex:1;min-width:0"><strong>{{ $incident->title }}</strong><div class="muted mono" style="font-size:.8rem">{{ $incident->reference }} · {{ $incident->type->label() }} · {{ $incident->created_at->format('d/m/Y') }}</div></div><span class="badge {{ $incident->severity->badge() }}">{{ $incident->severity->label() }}</span><span class="badge {{ $incident->status->badge() }}">{{ $incident->status->label() }}</span></div>
            @empty
            <p class="muted" style="padding:16px 0">Vous n'avez encore signalé aucun incident. <a href="{{ route('incidents.create') }}">Signaler un incident</a></p>
            @endforelse
          </div>
        </div>
        <div class="grid g2">
          <div class="card pad"><h3 style="margin-bottom:10px">Mes contributions</h3>
            <div class="budget-row"><span>Réservoir de La Soukra</span><span class="mono">50 TND</span></div>
            <div class="budget-row"><span>Conduite Avenue de la Liberté</span><span class="mono">30 TND</span></div>
            <div class="budget-row" style="border:0;font-weight:700"><span>Total</span><span class="mono">80 TND</span></div></div>
          <div class="card pad"><h3 style="margin-bottom:4px">Notifications</h3>
            <div class="pref"><span>Changement de statut de mes signalements</span><label class="toggle"><input type="checkbox" checked aria-label="Statut"><span></span></label></div>
            <div class="pref"><span>Coupures programmées dans ma commune</span><label class="toggle"><input type="checkbox" checked aria-label="Coupures"><span></span></label></div>
            <div class="pref" style="border:0"><span>Avancement des projets soutenus</span><label class="toggle"><input type="checkbox" aria-label="Projets"><span></span></label></div></div>
        </div>
      </div>
    </div>
  </div>
</section>
@endsection
