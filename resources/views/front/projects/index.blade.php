@extends('layouts.front.app')

@section('title', 'Projets')

@section('content')
<section class="screen" data-page="projets">
  <div class="wrap">
    <div class="screen-head"><div class="stack" style="gap:8px"><span class="eyebrow">Rénovation et décontamination</span><h2>Projets à financer</h2><p class="muted">Chaque dinar collecté est affecté au projet choisi. Les dépenses sont publiées à chaque étape des travaux.</p></div>
      <select class="input" style="width:auto" aria-label="Trier"><option>Plus urgents d'abord</option><option>Presque financés</option><option>Plus récents</option></select></div>
    <div class="grid g3" id="allProjects"></div>
  </div>
</section>
@endsection
