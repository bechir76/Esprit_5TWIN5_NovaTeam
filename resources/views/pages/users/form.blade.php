@extends('layouts.layout')

{{-- Formulaire partagé par create (user non enregistré) et edit --}}
@php
    $editing = $user->exists;
    $title = $editing ? 'Modifier '.$user->name : 'Nouvel utilisateur';
    $organizations = ['' => 'Aucune (citoyen)'] + collect(\App\Models\User::ORGANIZATIONS)->mapWithKeys(fn ($org) => [$org => $org])->all();
    if ($user->organization && ! isset($organizations[$user->organization])) {
        $organizations[$user->organization] = $user->organization;
    }
@endphp

@section('title', $title)

@section('content')
<section data-page="user-form" class="flex flex-col gap-3">
  <x-page-header :title="$title" :crumb="'Console / Utilisateurs / '.($editing ? 'Modifier' : 'Nouveau')" />
  <form class="grid items-start gap-[18px] min-[1101px]:grid-cols-[minmax(0,1fr)_320px]" method="POST" action="{{ $editing ? route('admin.users.update', $user) : route('admin.users.store') }}" novalidate>
    @csrf
    @if ($editing)
    @method('PUT')
    @endif
    <div class="card">
      <div class="form-section"><h2>Informations personnelles</h2><div class="grid grid-fit-[220px] gap-3.5">
        <x-form.input name="name" label="Nom complet *" :value="$user->name" autocomplete="name" />
        <x-form.input name="email" label="E-mail *" type="email" :value="$user->email" autocomplete="email" />
        <x-form.input name="phone" label="Téléphone" type="tel" :value="$user->phone" />
        <x-form.input name="address" label="Adresse" :value="$user->address" />
      </div></div>
      <div class="form-section"><h2>Mot de passe</h2><div class="grid grid-fit-[220px] gap-3.5">
        <x-form.input name="password" :label="$editing ? 'Nouveau mot de passe' : 'Mot de passe *'" type="password" autocomplete="new-password"
          :hint="$editing ? 'Laisser vide pour conserver le mot de passe actuel.' : '8 caractères min., une majuscule, une minuscule, un chiffre.'" />
        <x-form.input name="password_confirmation" :label="$editing ? 'Confirmation' : 'Confirmation *'" type="password" autocomplete="new-password" />
      </div></div>
    </div>
    <aside class="flex flex-col gap-[18px]">
      <div class="card p-[18px] flex flex-col gap-3"><h2>Accès</h2>
        <x-form.select name="role" label="Rôle *" :value="$user->role->value" :options="\App\Enums\Role::options()" />
        <x-form.select name="organization" label="Organisation" :value="$user->organization" :options="$organizations" />
        <div @class(['[&_.input]:border-crit [&_.input]:bg-crit-soft' => $errors->has('is_active')])>
          <div class="flex flex-wrap items-center gap-2.5 justify-between"><span>Compte actif</span><label class="relative inline-block h-[22px] w-10 flex-none"><input type="hidden" name="is_active" value="0"><input class="peer sr-only" type="checkbox" name="is_active" value="1" @checked(old('is_active', $user->is_active)) aria-label="Actif"><span class="switch-track"></span></label></div>
          @error('is_active')<span class="text-[.78rem] font-medium text-crit-fg">{{ $message }}</span>@enderror
        </div>
      </div>
      <div class="flex flex-wrap items-center gap-2.5"><a class="btn btn-ghost flex-1 justify-center" href="{{ route('admin.users.index') }}">Annuler</a><button class="btn btn-primary flex-1 justify-center" type="submit">{{ $editing ? 'Enregistrer' : 'Créer' }}</button></div>
    </aside>
  </form>
</section>
@endsection
