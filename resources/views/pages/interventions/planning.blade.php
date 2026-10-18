@extends('layouts.layout')

{{-- Planning des interventions (FullCalendar) : évènements chargés depuis admin.interventions.events,
     colorés par statut ; un clic sur un créneau libre ouvre le formulaire avec la date préremplie. --}}
@section('title', 'Planning des interventions')

@section('content')
<section data-page="interventions-planning" class="flex flex-col gap-3">
  <x-page-header title="Planning des interventions" crumb="Console / Interventions / Planning">
    <div class="flex flex-wrap items-center gap-2.5"><a class="btn btn-ghost" href="{{ route('admin.interventions.index') }}">Liste</a><a class="btn btn-primary" href="{{ route('admin.interventions.create') }}">+ Planifier</a></div>
  </x-page-header>
  <div class="card">
    <div class="filter-bar">
      <select class="input" id="planningTeam" aria-label="Équipe">
        <option value="">Toutes les équipes</option>
        @foreach ($teams as $team)
        <option value="{{ $team }}">{{ $team }}</option>
        @endforeach
      </select>
      <span class="flex flex-wrap items-center gap-3 text-[.8rem] text-muted">
        @foreach (['Planifiée' => '#1CA7EC', 'En cours' => '#F4A261', 'Terminée' => '#2EC4B6', 'Annulée' => '#9AA9B5'] as $label => $color)
        <span class="flex items-center gap-1.5"><span class="inline-block size-3 rounded-full" style="background:{{ $color }}"></span>{{ $label }}</span>
        @endforeach
      </span>
    </div>
    <div class="p-[18px]"><div id="planning" data-events="{{ route('admin.interventions.events') }}" data-create="{{ route('admin.interventions.create') }}"></div></div>
  </div>
</section>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/@fullcalendar/core@6.1.15/locales/fr.global.min.js"></script>
<script>
  (() => {
    const el = document.getElementById('planning');
    const team = document.getElementById('planningTeam');
    const calendar = new FullCalendar.Calendar(el, {
      initialView: window.innerWidth < 768 ? 'listWeek' : 'timeGridWeek',
      locale: 'fr',
      firstDay: 1,
      height: 'auto',
      slotMinTime: '06:00:00',
      slotMaxTime: '22:00:00',
      nowIndicator: true,
      buttonText: { today: "Aujourd'hui", month: 'Mois', week: 'Semaine', list: 'Liste' },
      headerToolbar: { left: 'prev,next today', center: 'title', right: 'dayGridMonth,timeGridWeek,listWeek' },
      events: (info, success, failure) => {
        const params = new URLSearchParams({ start: info.startStr, end: info.endStr });
        if (team.value) params.set('team', team.value);
        fetch(`${el.dataset.events}?${params}`, { headers: { Accept: 'application/json' } })
          .then((r) => r.ok ? r.json() : Promise.reject(r))
          .then(success, failure);
      },
      // Créneau libre : formulaire de planification avec la date (et l'équipe filtrée) préremplies
      dateClick: (info) => {
        const params = new URLSearchParams({ date: info.dateStr });
        if (team.value) params.set('equipe', team.value);
        window.location = `${el.dataset.create}?${params}`;
      },
    });
    calendar.render();
    team.addEventListener('change', () => calendar.refetchEvents());
  })();
</script>
@endpush
