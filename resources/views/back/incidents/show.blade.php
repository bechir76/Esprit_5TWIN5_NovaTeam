@extends('layouts.back.app')

@section('title', 'Eau trouble et odeur de terre')

@section('content')
<section data-page="incident" class="stack">
  <x-page-header title="Eau trouble et odeur de terre" crumb="Console / Incidents / INC-2026-0915">
    <x-slot:badges><x-status-badge value="Critique" dot /><x-status-badge value="Signalé" /></x-slot:badges>
    <div class="row"><button class="btn btn-ghost" type="button">Modifier</button><button class="btn btn-danger" type="button" data-del="INC-2026-0915" data-action="{{ route('admin.incidents.destroy', 1) }}">Supprimer</button></div>
  </x-page-header>
  <div class="detail">
    <div class="stack" style="gap:18px">
      <div class="card card-b stack" style="gap:16px">
        <div class="kv">
          <div><span class="label">Type</span><span>⚠️ Contamination</span></div>
          <div><span class="label">Infrastructure liée</span><a href="{{ route('admin.infrastructures.index') }}" style="color:var(--info-fg)">Forage de Mornag F3</a></div>
          <div><span class="label">Localisation</span><span>Cité Ennour, Ben Arous</span></div>
          <div><span class="label">Déclarant</span><span>Mehdi Gharbi · citoyen</span></div>
          <div><span class="label">Signalé le</span><span class="mono">03/10/2026 07:12</span></div>
          <div><span class="label">Foyers concernés</span><span class="mono">≈ 1 850</span></div>
        </div>
        <div><span class="label">Description</span><p style="margin-top:6px;max-width:70ch">L'eau sort du robinet légèrement jaunâtre avec une odeur de terre mouillée depuis hier soir. Plusieurs voisins de l'immeuble constatent la même chose.</p></div>
        <div><span class="label">Photos (3)</span><div class="photos" style="margin-top:8px"><div style="background:linear-gradient(135deg,#C9A15A,#8C6A2F)"></div><div style="background:linear-gradient(135deg,#B89A6A,#6F5733)"></div><div style="background:linear-gradient(135deg,#7BA0B6,#3F6880)"></div></div></div>
      </div>
      <div class="card"><div class="card-h"><h2>Résumé généré par l'IA</h2><span class="badge b-info">5 signalements analysés</span></div><div class="card-b"><div class="summary">Cinq habitants de la cité Ennour décrivent une eau jaunâtre à odeur terreuse depuis le 2 octobre au soir. Les signalements coïncident avec la hausse du nitrate mesurée au forage F3 (62 mg/L) et une baisse du chlore résiduel à 0,08 mg/L. Cause probable : infiltration après les pluies du 1er octobre. Priorité recommandée : <strong>immédiate</strong>.</div></div></div>
      <div class="card"><div class="card-h"><h2>Historique</h2></div><div class="card-b timeline">
        <div class="tl done"><strong>Signalement reçu</strong> via l'application citoyenne<div class="muted">03/10 07:12</div></div>
        <div class="tl done"><strong>Classé automatiquement</strong> « Contamination », gravité critique (confiance 91 %)<div class="muted">03/10 07:12 · IA</div></div>
        <div class="tl done"><strong>Distribution suspendue</strong> au forage F3 par K. Trabelsi<div class="muted">03/10 07:40</div></div>
        <div class="tl" style="padding-bottom:0"><strong>En attente d'assignation</strong> d'une équipe de prélèvement<div class="muted">maintenant</div></div>
      </div></div>
    </div>
    <aside class="stack" style="gap:18px">
      <form class="card card-b stack" method="POST" action="{{ route('admin.incidents.update', 1) }}" novalidate>
        @csrf
        @method('PUT')
        <h2>Traitement</h2>
        <x-form.select name="status" label="Statut" value="En intervention" :options="['Signalé', 'Qualifié', 'En intervention', 'Résolu', 'Rejeté']" />
        <x-form.select name="team" label="Équipe assignée" :options="['Équipe qualité · Ben Arous', 'Équipe réseau · Ariana', 'Équipe d\'astreinte']" />
        <x-form.input name="scheduled_at" label="Intervention prévue" type="datetime-local" value="2026-10-03T10:00" />
        <x-form.textarea name="comment" label="Commentaire interne" value="Prélèvements à faire sur 3 points du réseau + citerne mobile pour la cité Ennour." />
        <label class="row" style="gap:8px;font-size:.86rem"><input type="checkbox" name="notify_reporter" value="1" @checked(old('notify_reporter', true))> Notifier le déclarant</label>
        <button class="btn btn-primary" type="submit" style="justify-content:center">Mettre à jour</button>
      </form>
      <div class="card card-b stack"><h2>Localisation</h2><div class="mapbox" style="height:160px"><svg viewBox="0 0 300 160" width="100%" height="100%" preserveAspectRatio="xMidYMid slice"><rect width="300" height="160" fill="var(--surface-2)"/><path d="M0 60 L300 80 M120 0 L140 160" stroke="var(--line)" stroke-width="10"/><circle cx="160" cy="75" r="22" fill="var(--crit)" opacity=".2"/><circle cx="160" cy="75" r="8" fill="var(--crit)" stroke="#fff" stroke-width="3"/></svg></div></div>
    </aside>
  </div>
</section>
@endsection
