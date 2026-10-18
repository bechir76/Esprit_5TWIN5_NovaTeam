@extends('layouts.layout')

@section('title', 'Qualité de l\'eau')

@section('content')
<section data-page="water-quality">
  <div class="bg-hero pt-11 pb-[52px] text-white">
    <div class="mx-auto max-w-[1200px] px-5">
      <span class="kicker text-[#7FD8FF]">Surveillance en temps réel</span>
      <h1 class="mt-3 mb-3.5">Qualité de l'eau potable</h1>
      <p class="text-[#C7DDEB] max-w-[56ch] text-[1.05rem]">
        Dernières analyses validées par zone. Les paramètres sont comparés aux seuils réglementaires en vigueur.
      </p>
    </div>
  </div>

  <div class="mx-auto max-w-[1200px] px-5 pt-10 pb-16">
    {{-- Filtre par zone et analyses : composant Livewire (app/Livewire/WaterQualityFilter.php) --}}
    <livewire:water-quality-filter />
  </div>
</section>
@endsection
