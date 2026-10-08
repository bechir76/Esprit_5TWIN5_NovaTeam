@extends('layouts.front.app')

@section('title', 'Inscription')

@section('content')
<section class="pt-10 pb-16" data-page="inscription">
  <div class="mx-auto max-w-[1200px] px-5">
    <div class="grid min-h-[640px] overflow-hidden rounded-panel border border-line bg-surface min-[861px]:grid-cols-2">
      @include('auth.partials.side', [
          'title' => 'Devenez les yeux du réseau.',
          'text' => 'Un compte vous permet de suivre vos signalements et de recevoir les alertes de coupure de votre quartier.',
      ])

      <form class="mx-auto flex w-full max-w-[460px] flex-col justify-center gap-[18px] px-5 py-7 min-[861px]:p-11" method="POST" action="{{ route('register') }}" novalidate>
        @csrf
        <h2>Créer un compte</h2>

        <div class="grid grid-cols-2 gap-3">
          <x-form.input name="first_name" label="Prénom" autocomplete="given-name" autofocus />
          <x-form.input name="last_name" label="Nom" autocomplete="family-name" />
        </div>
        <x-form.input name="email" label="Adresse e-mail" type="email" autocomplete="username" />
        <div class="grid grid-cols-2 gap-3">
          <x-form.input name="phone" label="Téléphone" type="tel" autocomplete="tel" hint="Facultatif" />
          <x-form.input name="address" label="Adresse" autocomplete="street-address" hint="Facultatif" />
        </div>
        <x-form.input name="password" label="Mot de passe" type="password" autocomplete="new-password" hint="8 caractères minimum, dont un chiffre, une minuscule et une majuscule." />
        <x-form.input name="password_confirmation" label="Confirmer le mot de passe" type="password" autocomplete="new-password" />

        <div class="flex flex-col gap-1.5">
          <label class="flex items-center gap-2.5 text-[.92rem]"><input class="size-4 accent-water" type="checkbox" name="terms" value="1" @checked(old('terms'))> J'accepte les conditions d'utilisation</label>
          @error('terms')<span class="text-[.8rem] font-medium text-crit-fg">{{ $message }}</span>@enderror
        </div>

        <button class="btn btn-primary justify-center" type="submit">Créer mon compte</button>
        <p class="text-center text-[.9rem] text-muted">Déjà inscrit ? <a class="text-water underline" href="{{ route('login') }}">Se connecter</a></p>
      </form>
    </div>
  </div>
</section>
@endsection
