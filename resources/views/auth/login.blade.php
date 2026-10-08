@extends('layouts.front.app')

@section('title', 'Connexion')

@section('content')
<section class="pt-10 pb-16" data-page="connexion">
  <div class="mx-auto max-w-[1200px] px-5">
    <div class="grid min-h-[640px] overflow-hidden rounded-panel border border-line bg-surface min-[861px]:grid-cols-2">
      @include('auth.partials.side', [
          'title' => 'Bon retour parmi nous.',
          'text' => 'Retrouvez vos signalements, leur statut et les projets que vous soutenez.',
          'note' => '<strong>3 incidents</strong> signalés près de chez vous cette semaine, dont 2 déjà résolus.',
      ])

      <form class="mx-auto flex w-full max-w-[460px] flex-col justify-center gap-[18px] px-5 py-7 min-[861px]:p-11" method="POST" action="{{ route('login') }}" novalidate>
        @csrf
        <div>
          <h2>Connexion</h2>
          <p class="mt-1.5 text-muted">Accédez à votre espace citoyen.</p>
        </div>

        <x-form.input name="email" label="Adresse e-mail" type="email" autocomplete="username" autofocus />
        <x-form.input name="password" label="Mot de passe" type="password" autocomplete="current-password">
          <x-slot:aside><a class="text-[.82rem] text-water hover:underline" href="{{ route('password.request') }}">Mot de passe oublié ?</a></x-slot:aside>
        </x-form.input>

        <label class="flex items-center gap-2.5 text-[.92rem]"><input class="size-4 accent-water" type="checkbox" name="remember" value="1" @checked(old('remember'))> Se souvenir de moi</label>

        <button class="btn btn-primary justify-center" type="submit">Se connecter</button>
        <p class="text-center text-[.9rem] text-muted">Pas encore de compte ? <a class="text-water underline" href="{{ route('register') }}">Créer un compte</a></p>
      </form>
    </div>
  </div>
</section>
@endsection
