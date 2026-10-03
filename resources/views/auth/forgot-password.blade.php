@extends('layouts.front.app')

@section('title', 'Mot de passe oublié')

@section('content')
<section class="screen" data-page="oubli">
  <div class="wrap">
    <div class="card" style="max-width:480px;margin:0 auto"><form class="auth-form" method="POST" action="{{ route('password.email') }}" novalidate>
      @csrf
      <div><h2>Mot de passe oublié</h2><p class="muted" style="margin-top:6px">Indiquez votre adresse e-mail : nous vous enverrons un lien pour choisir un nouveau mot de passe.</p></div>
      <x-form.input name="email" label="Adresse e-mail" type="email" />
      @if (session('status'))
      <div class="card" style="padding:12px 14px;background:var(--ok-soft);border-color:transparent;color:#13877D;font-size:.88rem">Si un compte existe pour cette adresse, un lien de réinitialisation vient d'être envoyé.</div>
      @endif
      <button class="btn btn-primary" style="justify-content:center" type="submit">Envoyer le lien</button>
      <a href="{{ route('login') }}" class="muted" style="text-align:center;font-size:.9rem">← Retour à la connexion</a>
    </form></div>
  </div>
</section>
@endsection
