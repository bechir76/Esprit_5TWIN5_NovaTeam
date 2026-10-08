@extends('layouts.back.app')

@section('title', 'Paramètres')

@section('content')
<section data-page="parametres" class="flex flex-col gap-3">
  <x-page-header title="Paramètres" />
  <form class="card" method="POST" action="{{ route('admin.settings.update') }}" novalidate>
    @csrf
    @method('PUT')
    <div class="form-section"><h2>Seuils d'alerte qualité</h2><div class="grid grid-fit-[220px] gap-3.5">
      <x-form.input name="chlorine_min" label="Chlore résiduel min. (mg/L)" class="font-mono" value="0,10" />
      <x-form.input name="turbidity_max" label="Turbidité max. (NTU)" class="font-mono" value="1,0" />
      <x-form.input name="nitrate_max" label="Nitrate max. (mg/L)" class="font-mono" value="50" />
      <x-form.input name="pressure_min" label="Pression min. réseau (bar)" class="font-mono" value="1,5" />
    </div><div class="flex flex-wrap items-center gap-2.5 justify-end"><button class="btn btn-primary" type="submit">Enregistrer</button></div></div>
  </form>
</section>
@endsection
