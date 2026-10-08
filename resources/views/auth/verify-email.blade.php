@extends('layouts.front.app')

@section('title', 'Vérifier mon adresse e-mail')

@section('content')
<section class="pt-10 pb-16" data-page="verification">
  <div class="mx-auto max-w-[1200px] px-5">
    <div class="card mx-auto flex max-w-[480px] flex-col gap-[18px] px-5 py-7 sm:p-11">
      <div>
        <h2>Vérifiez votre adresse e-mail</h2>
        <p class="mt-1.5 text-muted">Merci pour votre inscription ! Cliquez sur le lien que nous venons de vous envoyer par e-mail pour confirmer votre adresse. Vous ne l'avez pas reçu ? Nous pouvons vous en envoyer un autre.</p>
      </div>

      <div class="flex flex-wrap items-center justify-between gap-3">
        <form method="POST" action="{{ route('verification.send') }}">
          @csrf
          <button class="btn btn-primary" type="submit">Renvoyer le lien</button>
        </form>
        <form method="POST" action="{{ route('logout') }}">
          @csrf
          <button class="text-[.9rem] text-muted underline hover:text-ink" type="submit">Se déconnecter</button>
        </form>
      </div>
    </div>
  </div>
</section>
@endsection
