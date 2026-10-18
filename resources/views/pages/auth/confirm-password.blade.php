@extends('layouts.layout')

@section('title', 'Confirmer le mot de passe')

@section('content')
<section class="pt-10 pb-16" data-page="confirmation">
  <div class="mx-auto max-w-[1200px] px-5">
    <div class="card mx-auto max-w-[480px]">
      <form class="flex flex-col gap-[18px] px-5 py-7 sm:p-11" method="POST" action="{{ route('password.confirm') }}" novalidate>
        @csrf
        <div>
          <h2>Confirmer le mot de passe</h2>
          <p class="mt-1.5 text-muted">Cette zone est sécurisée : confirmez votre mot de passe pour continuer.</p>
        </div>

        <x-form.input name="password" label="Mot de passe" type="password" autocomplete="current-password" autofocus />

        <button class="btn btn-primary justify-center" type="submit">Confirmer</button>
      </form>
    </div>
  </div>
</section>
@endsection
