@extends('layouts.back.app')

@section('title', 'Rôles et permissions')

@section('content')
<section data-page="roles" class="flex flex-col gap-3">
  <x-page-header title="Rôles et permissions" />
  <div class="grid items-start gap-[18px] md:grid-cols-[minmax(220px,280px)_minmax(0,1fr)]" id="rolesGrid">
    <div class="card flex flex-col gap-0.5 p-[18px]">
      @foreach ($roles as ['role' => $role, 'count' => $count])
      <a href="{{ route('admin.users.index', ['role' => $role->value]) }}" @class(['flex items-center justify-between gap-3 rounded-lg px-3.5 py-3 hover:bg-surface-2', 'bg-water-soft hover:bg-water-soft' => $role === \App\Enums\Role::Gestionnaire]) title="Voir les utilisateurs « {{ $role->label() }} »"><span><strong>{{ $role->label() }}</strong><br><span class="text-[.78rem] text-muted">{{ $role->description() }}</span></span><span class="font-mono text-muted">{{ number_format($count, 0, ',', ' ') }}</span></a>
      @endforeach
    </div>
    <div class="card">
      <div class="card-header"><h2>Permissions · Gestionnaire réseau</h2><x-status-badge value="Lecture seule" variant="b-mute" /></div>
      {{-- Cases générées par back.js (name="permissions[Module][]") ; matrice d'exemple non modifiable --}}
      <fieldset class="m-0 min-w-0 border-0 p-0" disabled>
        <div class="overflow-x-auto"><table class="data-table min-w-[560px] [&_th]:text-center [&_th:first-child]:text-left"><thead><tr><th>Module</th><th>Voir</th><th>Créer</th><th>Modifier</th><th>Supprimer</th></tr></thead><tbody id="matrix"></tbody></table></div>
      </fieldset>
    </div>
  </div>
</section>
@endsection
