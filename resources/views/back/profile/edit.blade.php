@extends('layouts.back.app')

@section('title', 'Mon profil')

@section('content')
<section data-page="profil" class="stack">
  <x-page-header title="Mon profil" />
  <div class="grid" style="grid-template-columns:repeat(auto-fit,minmax(min(100%,380px),1fr));align-items:start">
    <form class="card" method="POST" action="{{ route('admin.profile.update') }}" novalidate>
      @csrf
      @method('PUT')
      <div class="fsec"><div class="row"><span class="av" style="background:{{ $user->role->color() }};width:64px;height:64px;font-size:1.3rem">{{ $user->initials() }}</span><div><h2>{{ $user->name }}</h2><span class="muted">{{ $user->role->label() }}@if ($user->organization) · {{ $user->organization }}@endif</span></div></div>
      <div class="fgrid">
        <x-form.input name="name" label="Nom complet" :value="$user->name" autocomplete="name" />
        <x-form.input name="email" label="E-mail" type="email" :value="$user->email" autocomplete="email" />
        <x-form.input name="phone" label="Téléphone" type="tel" :value="$user->phone" />
        <x-form.input name="address" label="Adresse" :value="$user->address" />
      </div>
      <div class="row" style="justify-content:flex-end"><button class="btn btn-primary" type="submit">Enregistrer</button></div></div>
    </form>
    <form class="card" method="POST" action="{{ route('admin.password.update') }}" novalidate>
      @csrf
      @method('PUT')
      <div class="fsec"><h2>Mot de passe</h2>
        <x-form.input name="current_password" label="Mot de passe actuel" type="password" autocomplete="current-password" />
        <x-form.input name="password" label="Nouveau mot de passe" type="password" autocomplete="new-password" hint="8 caractères min., une majuscule, une minuscule, un chiffre." />
        <x-form.input name="password_confirmation" label="Confirmation" type="password" autocomplete="new-password" />
        <div class="row" style="justify-content:flex-end"><button class="btn btn-navy" type="submit">Modifier</button></div></div>
    </form>
  </div>
</section>
@endsection
