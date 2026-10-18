@extends('layouts.layout', ['bare' => true])

@section('title', 'Connexion')

@section('content')
<div class="grid min-h-full bg-surface min-[861px]:grid-cols-[1.1fr_1fr]" data-page="login">
  <div class="relative hidden flex-col justify-between gap-[30px] overflow-hidden bg-navy p-11 text-white min-[861px]:flex">
    <svg class="absolute inset-0 size-full opacity-25" viewBox="0 0 600 700" preserveAspectRatio="xMidYMid slice" aria-hidden="true"><g stroke="#1CA7EC" stroke-width="2" fill="none"><path d="M80 120 L260 200 L420 140 L540 260 M260 200 L300 380 L160 480 M300 380 L480 440 L520 600 M160 480 L120 640"/></g><g fill="#2EC4B6"><circle cx="80" cy="120" r="7"/><circle cx="260" cy="200" r="9"/><circle cx="420" cy="140" r="7"/><circle cx="540" cy="260" r="7"/><circle cx="300" cy="380" r="11"/><circle cx="160" cy="480" r="7"/><circle cx="480" cy="440" r="7"/><circle cx="520" cy="600" r="7"/><circle cx="120" cy="640" r="7"/></g><circle cx="480" cy="440" r="7" fill="#E63946"/></svg>

    <div class="relative flex items-center gap-2.5 font-display text-[1.1rem] font-extrabold">
      <svg width="30" height="30" viewBox="0 0 32 32" aria-hidden="true"><path d="M16 3C16 3 6 14.5 6 20.5a10 10 0 0 0 20 0C26 14.5 16 3 16 3Z" fill="#1CA7EC"/></svg>
      <span>AquaSecure<small class="block font-mono text-[.65rem] font-normal tracking-[.1em] text-[#7FB3D3]">CONSOLE DE GESTION</small></span>
    </div>
    <div class="relative">
      <h1 class="text-[2rem] text-white">Pilotez le réseau d'eau potable de votre territoire.</h1>
      <p class="mt-3 max-w-[44ch] text-[#BFD5E4]">Espace réservé aux municipalités, régies des eaux et équipes terrain.</p>
    </div>
    <div class="relative flex flex-wrap gap-7 text-[.85rem] text-[#BFD5E4]">
      @foreach ([['512', 'ouvrages suivis'], ['27', 'incidents ouverts'], ['99,2 %', 'conformité qualité']] as [$figure, $label])
      <span><strong class="font-mono text-[1.2rem] text-white">{{ $figure }}</strong><br>{{ $label }}</span>
      @endforeach
    </div>
  </div>

  <form class="m-auto flex w-full max-w-[400px] flex-col gap-4 px-6 py-10" method="POST" action="{{ route('admin.login.store') }}" novalidate>
    @csrf
    <div>
      <span class="font-mono text-[.7rem] tracking-[.08em] text-muted uppercase">Accès gestionnaire</span>
      <h1 class="mt-1.5">Connexion</h1>
    </div>

    <x-form.input name="email" label="E-mail professionnel" type="email" autocomplete="username" autofocus />
    <x-form.input name="password" label="Mot de passe" type="password" autocomplete="current-password" />

    <div class="flex flex-wrap items-center justify-between gap-2.5">
      <label class="flex items-center gap-2 text-[.86rem]"><input class="size-4 accent-water" type="checkbox" name="remember" value="1" @checked(old('remember'))> Rester connecté</label>
      <a class="text-[.84rem] text-info-fg hover:underline" href="{{ route('password.request') }}">Mot de passe oublié ?</a>
    </div>

    <button class="btn btn-navy justify-center py-3" type="submit">Se connecter</button>
    <p class="text-center text-[.78rem] text-muted">Vous êtes citoyen ? <a class="underline" href="{{ route('login') }}">Utilisez l'espace public.</a></p>
  </form>
</div>
@endsection
