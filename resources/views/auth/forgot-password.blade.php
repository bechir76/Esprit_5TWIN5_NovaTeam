@extends('layouts.front.app')

@section('title', 'Mot de passe oublié')

@section('content')
<section class="pt-10 pb-16" data-page="oubli">
  <div class="mx-auto max-w-[1200px] px-5">
    <div class="card mx-auto max-w-[480px]">
      <form class="flex flex-col gap-[18px] px-5 py-7 sm:p-11" method="POST" action="{{ route('password.email') }}" novalidate>
        @csrf
        <div>
          <h2>Mot de passe oublié</h2>
          <p class="mt-1.5 text-muted">Indiquez votre adresse e-mail : nous vous enverrons un lien pour choisir un nouveau mot de passe.</p>
        </div>

        <x-form.input name="email" label="Adresse e-mail" type="email" autocomplete="username" autofocus />

        @if (session('status'))
        <div class="rounded-card bg-ok-soft px-3.5 py-3 text-[.88rem] text-ok-fg">Si un compte existe pour cette adresse, un lien de réinitialisation vient d'être envoyé.</div>
        @endif

        <button class="btn btn-primary justify-center" type="submit">Envoyer le lien</button>
        <a class="text-center text-[.9rem] text-muted hover:text-ink" href="{{ route('login') }}">← Retour à la connexion</a>
      </form>
    </div>
  </div>
</section>
@endsection
