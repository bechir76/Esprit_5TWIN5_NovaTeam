@extends('layouts.front.app')

@section('title', 'Mon profil')

@section('content')
<section class="screen" data-page="profil">
  <div class="wrap">
    <div class="screen-head"><div class="stack" style="gap:8px"><span class="eyebrow">Espace citoyen</span><h2>Mon profil</h2></div></div>
    <div class="profile-layout">
      <aside class="card pad stack" style="gap:16px">
        <div class="stack" style="align-items:center;text-align:center;gap:8px"><div class="big-avatar">SB</div><strong>Sana Ben Ali</strong><span class="muted" style="font-size:.85rem">Membre depuis février 2025</span></div>
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
            <x-form.input name="first_name" label="Prénom" value="Sana" />
            <x-form.input name="last_name" label="Nom" value="Ben Ali" />
            <x-form.input name="email" label="E-mail" type="email" value="sana.benali@mail.tn" />
            <x-form.input name="phone" label="Téléphone" type="tel" value="22 451 870" />
            <x-form.input name="address" label="Adresse" value="12 rue Ibn Sina, Ariana" />
            <x-form.select name="commune" label="Commune" value="Ariana" :options="['Ariana', 'Tunis']" />
          </div>
          <div class="row" style="justify-content:flex-end"><button class="btn btn-primary" type="submit">Enregistrer</button></div>
        </form>
        <form class="card pad stack" style="gap:16px" method="POST" action="{{ route('password.update') }}" novalidate>
          @csrf
          @method('PUT')
          <h3>Changer le mot de passe</h3>
          <div class="grid g3" style="gap:14px">
            <x-form.input name="current_password" label="Mot de passe actuel" type="password" />
            <x-form.input name="password" label="Nouveau" type="password" />
            <x-form.input name="password_confirmation" label="Confirmation" type="password" />
          </div>
          <div class="row" style="justify-content:flex-end"><button class="btn btn-navy" type="submit">Mettre à jour</button></div>
        </form>
        <div class="card pad">
          <div class="row" style="justify-content:space-between;margin-bottom:8px"><h3>Mes signalements</h3><span class="muted mono" style="font-size:.85rem">4 au total</span></div>
          <div id="myReports"></div>
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
