@extends('layouts.layout')

@section('title', 'Signaler un incident')

@section('content')
<section class="pt-10 pb-16" data-page="signaler">
  <div class="mx-auto max-w-[1200px] px-5">
    <div class="mb-7 flex flex-wrap items-end justify-between gap-4 [&_p]:max-w-[60ch]"><div class="flex flex-col gap-2"><span class="kicker">Nouveau signalement</span><h2>Signaler un incident</h2><p class="text-muted">Les champs marqués d'un astérisque sont obligatoires. Une urgence sanitaire ? Appelez aussi le numéro vert de votre régie.</p></div></div>
    <div class="grid items-start gap-6 min-[981px]:grid-cols-[minmax(0,1fr)_340px]">
      <form class="card" id="reportForm" method="POST" action="{{ route('incidents.store') }}" enctype="multipart/form-data" novalidate>
        @csrf
        <div class="flex flex-col gap-[18px] border-line p-6 [&+&]:border-t">
          <div class="flex items-center gap-2.5 [&>span]:font-mono [&>span]:text-xs [&>span]:text-muted"><span>1/4</span><h3>Nature de l'incident</h3></div>
          <div @class(['flex flex-col gap-1.5 [&>label]:text-[.88rem] [&>label]:font-semibold', '[&_.input]:border-crit [&_.input]:bg-crit-soft' => $errors->has('type')])><label>Type d'incident *</label>
            <div class="grid grid-cols-3 gap-2" role="radiogroup">
              @foreach (\App\Enums\IncidentType::cases() as $k => $type)
              <input class="sr-only" type="radio" name="type" id="t{{ $k + 1 }}" value="{{ $type->value }}" @checked(old('type', \App\Enums\IncidentType::Fuite->value) === $type->value)><label class="choice-tile" for="t{{ $k + 1 }}">{{ $type->icon() }} {{ $type->label() }}</label>
              @endforeach
            </div>
            @error('type')<span class="flex items-center gap-1.5 text-[.8rem] font-medium text-crit">{{ $message }}</span>@enderror
          </div>
          <x-form.select name="severity" label="Niveau de gravité *" :value="\App\Enums\IncidentSeverity::Moyenne->value" :options="collect(\App\Enums\IncidentSeverity::cases())->mapWithKeys(fn ($s) => [$s->value => $s->description()])->all()" />
          <x-form.input name="title" label="Titre *" placeholder="Ex. : Fuite importante devant l'école Ibn Khaldoun" maxlength="120" />
          <x-form.textarea name="description" label="Description *" hint="Décrivez ce que vous voyez : couleur, odeur, débit, depuis quand (30 caractères au moins)." />
        </div>
        <div class="flex flex-col gap-[18px] border-line p-6 [&+&]:border-t">
          <div class="flex items-center gap-2.5 [&>span]:font-mono [&>span]:text-xs [&>span]:text-muted"><span>2/4</span><h3>Localisation</h3></div>
          <div class="grid grid-fit-[280px] gap-3.5">
            <x-form.input name="address" label="Adresse *" placeholder="Avenue Habib Bourguiba, Ariana" />
            <x-form.select name="infrastructure_id" label="Ouvrage concerné (si connu)" :options="['' => 'Je ne sais pas'] + $infrastructures" />
          </div>
          <div class="relative h-40 overflow-hidden rounded-[9px] border border-line bg-surface-2" aria-label="Position du signalement">
            <svg viewBox="0 0 600 160" width="100%" height="100%" preserveAspectRatio="xMidYMid slice"><rect width="600" height="160" fill="var(--color-surface-2)"/><path d="M0 60 L600 90 M0 130 L600 110 M180 0 L220 160 M420 0 L390 160" stroke="var(--color-line)" stroke-width="10"/><path d="M0 60 L600 90" stroke="var(--color-water)" stroke-width="2" stroke-dasharray="6 6" opacity=".6"/><circle cx="300" cy="75" r="22" fill="var(--color-water)" opacity=".2"/><circle cx="300" cy="75" r="8" fill="var(--color-water)" stroke="#fff" stroke-width="3"/></svg>
          </div>
          <div class="grid grid-fit-[280px] gap-3.5">
            <x-form.input name="latitude" label="Latitude" inputmode="decimal" placeholder="36.8660" />
            <x-form.input name="longitude" label="Longitude" inputmode="decimal" placeholder="10.1640" />
          </div>
          <button type="button" class="btn btn-ghost btn-sm self-start" id="geoBtn">📍 Utiliser ma position</button>
        </div>
        <div class="flex flex-col gap-[18px] border-line p-6 [&+&]:border-t">
          <div class="flex items-center gap-2.5 [&>span]:font-mono [&>span]:text-xs [&>span]:text-muted"><span>3/4</span><h3>Photo</h3></div>
          <div @class(['flex flex-col gap-1.5 [&>label]:text-[.88rem] [&>label]:font-semibold', '[&_.input]:border-crit [&_.input]:bg-crit-soft' => $errors->has('photo')])>
            <label class="cursor-pointer rounded-card border-2 border-dashed border-line p-[26px] text-center text-muted" for="photo"><strong>Ajoutez une photo</strong> en cliquant ici<br><span class="text-[.8rem]">JPG ou PNG, 5 Mo maximum</span><br><span class="font-mono text-[.8rem]" id="photoName"></span></label>
            <input type="file" id="photo" name="photo" accept="image/jpeg,image/png" hidden>
            @error('photo')<span class="flex items-center gap-1.5 text-[.8rem] font-medium text-crit">{{ $message }}</span>@enderror
          </div>
        </div>
        <div class="flex flex-col gap-[18px] border-line p-6 [&+&]:border-t">
          <div class="flex items-center gap-2.5 [&>span]:font-mono [&>span]:text-xs [&>span]:text-muted"><span>4/4</span><h3>Vos coordonnées</h3></div>
          <p class="text-muted text-[.9rem]">Signalement envoyé au nom de <strong>{{ auth()->user()->name }}</strong> ({{ auth()->user()->email }}). Suivez son traitement depuis <a class="underline" href="{{ route('profile.edit') }}">Mon profil</a> ; vos coordonnées ne sont jamais publiées.</p>
          <div class="flex flex-wrap items-center gap-3 justify-end"><button class="btn btn-primary" type="submit">Envoyer le signalement</button></div>
        </div>
      </form>
      <aside class="sticky top-[90px] rounded-card bg-linear-160 from-navy to-[#12507A] p-[22px] text-white" aria-live="polite">
        <div class="flex flex-wrap items-center gap-2.5"><span class="grid size-[34px] place-items-center rounded-[9px] bg-water">✦</span><div><strong>Assistant IA</strong><div class="text-[.8rem] text-[#9CBCD1]">Analyse de la description et des photos</div></div></div>
        <div class="mt-2.5 flex items-center justify-between gap-2.5 rounded-[9px] bg-white/8 px-3.5 py-3"><div><div class="text-[.78rem] text-[#9CBCD1]">Catégorie suggérée</div><strong>Fuite sur conduite</strong></div><span class="font-mono text-[.8rem] text-[#7FD8FF]">94 %</span></div>
        <div class="mt-2.5 flex items-center justify-between gap-2.5 rounded-[9px] bg-white/8 px-3.5 py-3"><div><div class="text-[.78rem] text-[#9CBCD1]">Gravité estimée</div><strong>Élevée</strong></div><span class="font-mono text-[.8rem] text-[#7FD8FF]">81 %</span></div>
        <div class="mt-2.5 flex items-center justify-between gap-2.5 rounded-[9px] bg-white/8 px-3.5 py-3"><div><div class="text-[.78rem] text-[#9CBCD1]">Ouvrage probable</div><strong>Conduite DN 300 · Ariana Nord</strong></div><span class="font-mono text-[.8rem] text-[#7FD8FF]">76 %</span></div>
        <p class="text-[.85rem] text-[#C7DDEB] mt-3.5">2 signalements similaires à moins de 200 m ces dernières 24 h. Le vôtre sera regroupé pour accélérer l'intervention.</p>
        <button class="btn btn-light btn-sm mt-3.5 w-full justify-center" type="button">Appliquer les suggestions</button>
      </aside>
    </div>
  </div>
</section>
@endsection
