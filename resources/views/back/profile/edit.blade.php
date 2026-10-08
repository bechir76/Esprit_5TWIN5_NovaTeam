@extends('layouts.back.app')

@section('title', 'Mon profil')

@section('content')
<section class="flex flex-col gap-5" data-page="profil">
  <x-page-header title="Mon profil" />

  <div class="grid items-start gap-[18px] lg:grid-cols-2">
    <form class="card flex flex-col gap-4 p-5" method="POST" action="{{ route('admin.profile.update') }}" novalidate>
      @csrf
      @method('PUT')
      <div class="flex items-center gap-3">
        <span class="grid size-16 flex-none place-items-center rounded-full text-[1.3rem] font-bold text-white" style="background:{{ $user->role->color() }}">{{ $user->initials() }}</span>
        <div><h2>{{ $user->name }}</h2><span class="text-muted">{{ $user->role->label() }}@if ($user->organization) · {{ $user->organization }}@endif</span></div>
      </div>
      <div class="grid gap-3.5 sm:grid-cols-2">
        <x-form.input name="name" label="Nom complet" :value="$user->name" autocomplete="name" />
        <x-form.input name="email" label="E-mail" type="email" :value="$user->email" autocomplete="email" />
        <x-form.input name="phone" label="Téléphone" type="tel" :value="$user->phone" autocomplete="tel" />
        <x-form.input name="address" label="Adresse" :value="$user->address" autocomplete="street-address" />
      </div>
      <div class="flex justify-end"><button class="btn btn-primary" type="submit">Enregistrer</button></div>
    </form>

    <form class="card flex flex-col gap-4 p-5" method="POST" action="{{ route('admin.password.update') }}" novalidate>
      @csrf
      @method('PUT')
      <h2>Mot de passe</h2>
      <x-form.input name="current_password" label="Mot de passe actuel" type="password" autocomplete="current-password" />
      <x-form.input name="password" label="Nouveau mot de passe" type="password" autocomplete="new-password" hint="8 caractères min., une majuscule, une minuscule, un chiffre." />
      <x-form.input name="password_confirmation" label="Confirmation" type="password" autocomplete="new-password" />
      <div class="flex justify-end"><button class="btn btn-navy" type="submit">Modifier</button></div>
    </form>
  </div>
</section>
@endsection
