@extends('layouts.back.app')

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
<section data-page="user-form" class="stack">
  <x-page-header :title="$title" :crumb="'Console / Utilisateurs / '.($editing ? 'Modifier' : 'Nouveau')" />
  <form class="form-layout" method="POST" action="{{ $editing ? route('admin.users.update', $user) : route('admin.users.store') }}" novalidate>
    @csrf
    @if ($editing)
    @method('PUT')
    @endif
    <div class="card">
      <div class="fsec"><h2>Informations personnelles</h2><div class="fgrid">
        <x-form.input name="name" label="Nom complet *" :value="$user->name" autocomplete="name" />
        <x-form.input name="email" label="E-mail *" type="email" :value="$user->email" autocomplete="email" />
        <x-form.input name="phone" label="Téléphone" type="tel" :value="$user->phone" />
        <x-form.input name="address" label="Adresse" :value="$user->address" />
      </div></div>
      <div class="fsec"><h2>Mot de passe</h2><div class="fgrid">
        <x-form.input name="password" :label="$editing ? 'Nouveau mot de passe' : 'Mot de passe *'" type="password" autocomplete="new-password"
          :hint="$editing ? 'Laisser vide pour conserver le mot de passe actuel.' : '8 caractères min., une majuscule, une minuscule, un chiffre.'" />
        <x-form.input name="password_confirmation" :label="$editing ? 'Confirmation' : 'Confirmation *'" type="password" autocomplete="new-password" />
      </div></div>
    </div>
    <aside class="stack" style="gap:18px">
      <div class="card card-b stack"><h2>Accès</h2>
        <x-form.select name="role" label="Rôle *" :value="$user->role->value" :options="\App\Enums\Role::options()" />
        <x-form.select name="organization" label="Organisation" :value="$user->organization" :options="$organizations" />
        <div @class(['is-invalid' => $errors->has('is_active')])>
          <div class="row between"><span>Compte actif</span><label class="toggle"><input type="hidden" name="is_active" value="0"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $user->is_active)) aria-label="Actif"><span></span></label></div>
          @error('is_active')<span class="error">{{ $message }}</span>@enderror
        </div>
      </div>
      <div class="row"><a class="btn btn-ghost" href="{{ route('admin.users.index') }}" style="flex:1;justify-content:center">Annuler</a><button class="btn btn-primary" type="submit" style="flex:1;justify-content:center">{{ $editing ? 'Enregistrer' : 'Créer' }}</button></div>
    </aside>
  </form>
</section>
@endsection
