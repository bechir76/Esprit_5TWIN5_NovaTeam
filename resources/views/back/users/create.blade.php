@extends('layouts.back.app')

@section('title', 'Nouvel utilisateur')

@section('content')
<section data-page="user-form" class="stack">
  <x-page-header title="Nouvel utilisateur" crumb="Console / Utilisateurs / Nouveau" />
  <form class="form-layout" method="POST" action="{{ route('admin.users.store') }}" novalidate>
    @csrf
    <div class="card">
      <div class="fsec"><h2>Informations personnelles</h2><div class="fgrid">
        <x-form.input name="first_name" label="Prénom *" />
        <x-form.input name="last_name" label="Nom *" />
        <x-form.input name="email" label="E-mail *" type="email" />
        <x-form.input name="phone" label="Téléphone" type="tel" />
      </div></div>
      <div class="fsec"><h2>Mot de passe</h2><div class="fgrid">
        <x-form.input name="password" label="Mot de passe *" type="password" hint="8 caractères min., une majuscule, un chiffre." />
        <x-form.input name="password_confirmation" label="Confirmation *" type="password" />
      </div><label class="row" style="gap:8px;font-size:.86rem"><input type="checkbox" name="send_activation_link" value="1" @checked(old('send_activation_link', true))> Envoyer un lien d'activation au lieu de définir le mot de passe</label></div>
    </div>
    <aside class="stack" style="gap:18px">
      <div class="card card-b stack"><h2>Accès</h2>
        <x-form.select name="role" label="Rôle *" value="Gestionnaire réseau" :options="['Administrateur', 'Gestionnaire réseau', 'Technicien terrain', 'Citoyen']" />
        <x-form.select name="organization" label="Organisation" :options="['Régie des eaux d\'Ariana', 'Municipalité de Tunis', 'Municipalité de Ben Arous', 'Aucune (citoyen)']" />
        <div class="row between"><span>Compte actif</span><label class="toggle"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', true)) aria-label="Actif"><span></span></label></div>
      </div>
      <div class="row"><a class="btn btn-ghost" href="{{ route('admin.users.index') }}" style="flex:1;justify-content:center">Annuler</a><button class="btn btn-primary" type="submit" style="flex:1;justify-content:center">Créer</button></div>
    </aside>
  </form>
</section>
@endsection
