@extends('layouts.front.app')

@section('title', 'Nouveau mot de passe')

@section('content')
<section class="pt-10 pb-16" data-page="reset">
  <div class="mx-auto max-w-[1200px] px-5">
    <div class="card mx-auto max-w-[480px]">
      <form class="flex flex-col gap-[18px] px-5 py-7 sm:p-11" method="POST" action="{{ route('password.store') }}" novalidate>
        @csrf
        <input type="hidden" name="token" value="{{ $request->route('token') }}">

        <h2>Choisir un nouveau mot de passe</h2>

        <x-form.input name="email" label="Adresse e-mail" type="email" :value="$request->email" autocomplete="username" readonly />
        <x-form.input name="password" label="Nouveau mot de passe" type="password" autocomplete="new-password" autofocus
                      hint="8 caractères minimum, dont un chiffre, une minuscule et une majuscule." />
        <x-form.input name="password_confirmation" label="Confirmer" type="password" autocomplete="new-password" />

        <button class="btn btn-primary justify-center" type="submit">Enregistrer le mot de passe</button>
      </form>
    </div>
  </div>
</section>
@endsection
