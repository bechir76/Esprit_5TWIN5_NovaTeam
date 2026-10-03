@extends('layouts.back.auth')

@section('title', 'Connexion')

@section('content')
<div class="login" data-page="login">
  <div class="login-side">
    <svg class="net" viewBox="0 0 600 700" preserveAspectRatio="xMidYMid slice" aria-hidden="true"><g stroke="#1CA7EC" stroke-width="2" fill="none"><path d="M80 120 L260 200 L420 140 L540 260 M260 200 L300 380 L160 480 M300 380 L480 440 L520 600 M160 480 L120 640"/></g><g fill="#2EC4B6"><circle cx="80" cy="120" r="7"/><circle cx="260" cy="200" r="9"/><circle cx="420" cy="140" r="7"/><circle cx="540" cy="260" r="7"/><circle cx="300" cy="380" r="11"/><circle cx="160" cy="480" r="7"/><circle cx="480" cy="440" r="7"/><circle cx="520" cy="600" r="7"/><circle cx="120" cy="640" r="7"/></g><circle cx="480" cy="440" r="7" fill="#E63946"/></svg>
    <div class="brand" style="padding:0"><svg width="30" height="30" viewBox="0 0 32 32"><path d="M16 3C16 3 6 14.5 6 20.5a10 10 0 0 0 20 0C26 14.5 16 3 16 3Z" fill="#1CA7EC"/></svg><span>AquaSecure<small>CONSOLE DE GESTION</small></span></div>
    <div><h1 style="font-size:2rem;color:#fff">Pilotez le réseau d'eau potable de votre territoire.</h1><p style="color:#BFD5E4;margin-top:12px;max-width:44ch">Espace réservé aux municipalités, régies des eaux et équipes terrain.</p></div>
    <div class="row" style="gap:28px;font-size:.85rem;color:#BFD5E4"><span><strong class="mono" style="color:#fff;font-size:1.2rem">512</strong><br>ouvrages suivis</span><span><strong class="mono" style="color:#fff;font-size:1.2rem">27</strong><br>incidents ouverts</span><span><strong class="mono" style="color:#fff;font-size:1.2rem">99,2 %</strong><br>conformité qualité</span></div>
  </div>
  <form class="login-form" method="POST" action="{{ route('admin.login.store') }}" novalidate>
    @csrf
    <div><span class="label">Accès gestionnaire</span><h1 style="margin-top:6px">Connexion</h1></div>
    <x-form.input name="email" label="E-mail professionnel" type="email" />
    <x-form.input name="password" label="Mot de passe" type="password" />
    <div class="row between"><label class="row" style="gap:8px;font-size:.86rem"><input type="checkbox" name="remember" value="1" @checked(old('remember'))> Rester connecté</label><a href="{{ route('password.request') }}" style="font-size:.84rem;color:var(--info-fg)">Mot de passe oublié ?</a></div>
    <button class="btn btn-navy" style="justify-content:center;padding:12px" type="submit">Se connecter</button>
    <p class="hint" style="text-align:center">Vous êtes citoyen ? Utilisez l'espace public.</p>
  </form>
</div>
@endsection
