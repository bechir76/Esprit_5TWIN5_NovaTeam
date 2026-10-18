@extends('layouts.layout')

@section('title', 'Double authentification')

@section('content')
<section class="pt-10 pb-16" data-page="deux-facteurs">
  <div class="mx-auto max-w-[1200px] px-5">
    <div class="card mx-auto max-w-[480px]">
      {{-- Code de l'application ou, à défaut, code de récupération (Alpine bascule entre les deux champs) --}}
      <form class="flex flex-col gap-[18px] px-5 py-7 sm:p-11" method="POST" action="{{ route('two-factor.login.store') }}" novalidate
            x-data="{ recovery: @js($errors->has('recovery_code')) }">
        @csrf
        <div>
          <h2>Double authentification</h2>
          <p class="mt-1.5 text-muted" x-show="! recovery">Saisissez le code à 6 chiffres affiché par votre application d'authentification.</p>
          <p class="mt-1.5 text-muted" x-show="recovery" x-cloak>Saisissez l'un de vos codes de récupération. Chaque code ne sert qu'une fois.</p>
        </div>

        <div x-show="! recovery">
          <x-form.input name="code" label="Code" inputmode="numeric" autocomplete="one-time-code" maxlength="6" autofocus />
        </div>
        <div x-show="recovery" x-cloak>
          <x-form.input name="recovery_code" label="Code de récupération" autocomplete="off" placeholder="A1B2C-D3E4F" />
        </div>

        <button class="btn btn-primary justify-center" type="submit">Se connecter</button>
        <button class="text-center text-[.9rem] text-muted underline hover:text-ink" type="button"
                x-on:click="recovery = ! recovery; $nextTick(() => $el.form.querySelector(recovery ? '#recovery_code' : '#code').focus())"
                x-text="recovery ? 'Utiliser le code de l\'application' : 'Utiliser un code de récupération'">Utiliser un code de récupération</button>
        <a class="text-center text-[.9rem] text-muted hover:text-ink" href="{{ route('login') }}">← Retour à la connexion</a>
      </form>
    </div>
  </div>
</section>
@endsection
