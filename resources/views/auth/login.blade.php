@extends('layouts.front.app')

@section('title', 'Connexion')

@section('content')
<section class="screen" data-page="connexion">
  <div class="wrap">
    <div class="auth">
      <div class="auth-side">
        <a class="brand" style="color:#fff" href="{{ route('home') }}"><svg width="30" height="30" viewBox="0 0 32 32"><path d="M16 3C16 3 6 14.5 6 20.5a10 10 0 0 0 20 0C26 14.5 16 3 16 3Z" fill="#1CA7EC"/></svg>AquaSecure</a>
        <div><h2 style="color:#fff">Bon retour parmi nous.</h2><p style="color:#C7DDEB;margin-top:12px;max-width:40ch">Retrouvez vos signalements, leur statut et les projets que vous soutenez.</p></div>
        <div class="gauge-card" style="font-size:.9rem"><strong>3 incidents</strong> signalés près de chez vous cette semaine, dont 2 déjà résolus.</div>
      </div>
      <form class="auth-form" method="POST" action="{{ route('login') }}" novalidate>
        @csrf
        <div><h2>Connexion</h2><p class="muted" style="margin-top:6px">Accédez à votre espace citoyen.</p></div>
        <x-form.input name="email" label="Adresse e-mail" type="email" />
        <x-form.input name="password" label="Mot de passe" type="password">
          <x-slot:aside><a href="{{ route('password.request') }}" style="font-size:.82rem;color:var(--water)">Mot de passe oublié ?</a></x-slot:aside>
        </x-form.input>
        <label class="check"><input type="checkbox" name="remember" value="1" @checked(old('remember'))> Se souvenir de moi</label>
        <button class="btn btn-primary" style="justify-content:center" type="submit">Se connecter</button>
        <p class="muted" style="text-align:center;font-size:.9rem">Pas encore de compte ? <a href="{{ route('register') }}" style="color:var(--water)">Créer un compte</a></p>
      </form>
    </div>
  </div>
</section>
@endsection
