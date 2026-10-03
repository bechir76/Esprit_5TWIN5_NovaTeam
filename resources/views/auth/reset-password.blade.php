@extends('layouts.front.app')

@section('title', 'Nouveau mot de passe')

@section('content')
<section class="screen" data-page="reset">
  <div class="wrap">
    <div class="card" style="max-width:480px;margin:0 auto"><form class="auth-form" method="POST" action="{{ route('password.store') }}" novalidate>
      @csrf
      <input type="hidden" name="token" value="{{ request()->route('token') }}">
      <div><h2>Choisir un nouveau mot de passe</h2></div>
      <x-form.input name="email" label="Adresse e-mail" type="email" :value="request('email')" readonly />
      <x-form.input name="password" label="Nouveau mot de passe" type="password">
        <div class="progress" style="height:5px"><span style="width:70%;background:var(--teal)"></span></div><span class="hint">Robustesse : bonne</span>
      </x-form.input>
      <x-form.input name="password_confirmation" label="Confirmer" type="password" />
      <button class="btn btn-primary" style="justify-content:center" type="submit">Enregistrer le mot de passe</button>
    </form></div>
  </div>
</section>
@endsection
