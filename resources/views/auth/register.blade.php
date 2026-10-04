@extends('layouts.front.app')

@section('title', 'Inscription')

@section('content')
<section class="screen" data-page="inscription">
  <div class="wrap">
    <div class="auth">
      <div class="auth-side">
        <a class="brand" style="color:#fff" href="{{ route('home') }}"><svg width="30" height="30" viewBox="0 0 32 32"><path d="M16 3C16 3 6 14.5 6 20.5a10 10 0 0 0 20 0C26 14.5 16 3 16 3Z" fill="#1CA7EC"/></svg>AquaSecure</a>
        <div><h2 style="color:#fff">Devenez les yeux du réseau.</h2><p style="color:#C7DDEB;margin-top:12px;max-width:40ch">Un compte vous permet de suivre vos signalements et de recevoir les alertes de coupure de votre quartier.</p></div>
        <div></div>
      </div>
      <form class="auth-form" method="POST" action="{{ route('register') }}" novalidate>
        @csrf
        <div><h2>Créer un compte</h2></div>
        <div class="grid g2" style="gap:12px;grid-template-columns:1fr 1fr">
          <x-form.input name="first_name" label="Prénom" />
          <x-form.input name="last_name" label="Nom" />
        </div>
        <x-form.input name="email" label="Adresse e-mail" type="email" />
        <x-form.input name="password" label="Mot de passe" type="password" hint="8 caractères minimum, dont un chiffre et une majuscule." />
        <x-form.input name="password_confirmation" label="Confirmer le mot de passe" type="password" />
        <label class="check"><input type="checkbox" name="terms" value="1" @checked(old('terms'))> J'accepte les conditions d'utilisation</label>
        <button class="btn btn-primary" style="justify-content:center" type="submit">Créer mon compte</button>
      </form>
    </div>
  </div>
</section>
@endsection
