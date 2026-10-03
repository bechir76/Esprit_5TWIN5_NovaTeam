@extends('layouts.back.app')

@section('title', 'Mon profil')

@section('content')
<section data-page="profil" class="stack">
  <x-page-header title="Mon profil" />
  <div class="grid" style="grid-template-columns:repeat(auto-fit,minmax(min(100%,380px),1fr));align-items:start">
    <form class="card" method="POST" action="{{ route('admin.profile.update') }}" novalidate>
      @csrf
      @method('PUT')
      <div class="fsec"><div class="row"><span class="av" style="background:var(--navy-2);width:64px;height:64px;font-size:1.3rem">KT</span><div><h2>Karim Trabelsi</h2><span class="muted">Administrateur · Régie des eaux d'Ariana</span></div><button class="btn btn-ghost btn-sm" type="button" style="margin-left:auto">Changer la photo</button></div>
      <div class="fgrid">
        <x-form.input name="first_name" label="Prénom" value="Karim" />
        <x-form.input name="last_name" label="Nom" value="Trabelsi" />
        <x-form.input name="email" label="E-mail" type="email" value="k.trabelsi@regie-ariana.tn" />
        <x-form.input name="phone" label="Téléphone" type="tel" value="71 234 567" />
      </div>
      <div class="row" style="justify-content:flex-end"><button class="btn btn-primary" type="submit">Enregistrer</button></div></div>
    </form>
    <form class="card" method="POST" action="{{ route('admin.password.update') }}" novalidate>
      @csrf
      @method('PUT')
      <div class="fsec"><h2>Mot de passe</h2>
        <x-form.input name="current_password" label="Mot de passe actuel" type="password" />
        <x-form.input name="password" label="Nouveau mot de passe" type="password" />
        <x-form.input name="password_confirmation" label="Confirmation" type="password" />
        <div class="row" style="justify-content:flex-end"><button class="btn btn-navy" type="submit">Modifier</button></div></div>
    </form>
  </div>
</section>
@endsection
