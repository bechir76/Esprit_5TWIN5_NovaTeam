@extends('layouts.layout')

@section('title', 'Utilisateurs')

@php
    // Icônes des actions (mêmes tracés que back.js)
    $icon = fn (string $paths) => '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">'.$paths.'</svg>';
    $edit = $icon('<path d="M12 20h9M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z"/>');
    $ban = $icon('<circle cx="12" cy="12" r="9"/><path d="m5.6 5.6 12.8 12.8"/>');
    $trash = $icon('<path d="M3 6h18M8 6V4h8v2M19 6l-1 14H6L5 6"/>');
@endphp

@section('content')
<section data-page="users" class="flex flex-col gap-3">
  <x-page-header title="Utilisateurs">
    <a class="btn btn-primary" href="{{ route('admin.users.create') }}">+ Ajouter un utilisateur</a>
  </x-page-header>
  <div class="grid gap-[18px] grid-fit-[170px]">
    <x-kpi-card label="Total" :value="number_format($counts->total, 0, ',', ' ')" />
    <x-kpi-card label="Gestionnaires" :value="(int) $counts->gestionnaires" />
    <x-kpi-card label="Techniciens" :value="(int) $counts->techniciens" />
    <x-kpi-card label="Suspendus" :value="(int) $counts->suspended" />
  </div>
  <div class="card">
    <form class="filter-bar" method="GET" action="{{ route('admin.users.index') }}">
      <input class="input min-w-[200px] flex-1" type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Nom ou e-mail…" aria-label="Rechercher">
      <select class="input" name="role" aria-label="Rôle" onchange="this.form.submit()">
        <option value="">Tous les rôles</option>
        @foreach (\App\Enums\Role::cases() as $role)
        <option value="{{ $role->value }}" @selected(($filters['role'] ?? '') === $role->value)>{{ $role->label() }}</option>
        @endforeach
      </select>
      <select class="input" name="status" aria-label="Statut" onchange="this.form.submit()">
        <option value="">Tous les statuts</option>
        <option value="active" @selected(($filters['status'] ?? '') === 'active')>Actif</option>
        <option value="suspended" @selected(($filters['status'] ?? '') === 'suspended')>Suspendu</option>
      </select>
      <button class="btn btn-ghost" type="submit">Filtrer</button>
      @if (array_filter($filters))
      <a class="btn btn-ghost" href="{{ route('admin.users.index') }}">Réinitialiser</a>
      @endif
    </form>
    <div class="overflow-x-auto"><table class="data-table"><thead><tr><th>Utilisateur</th><th>Rôle</th><th>Organisation</th><th>Statut</th><th>Inscription</th><th></th></tr></thead><tbody>
      @forelse ($users as $user)
      <tr>
        <td><div class="flex items-center gap-2.5 flex-nowrap"><span class="grid size-9 flex-none place-items-center rounded-full text-[.8rem] font-bold text-white" style="background:{{ $user->role->color() }}">{{ $user->initials() }}</span><span><strong>{{ $user->name }}</strong><br><span class="text-muted text-[.8rem]">{{ $user->email }}</span></span></div></td>
        <td><x-status-badge :value="$user->role->label()" :variant="$user->role->badge()" /></td>
        <td>{{ $user->organization ?? '—' }}</td>
        <td><x-status-badge :value="$user->is_active ? 'Actif' : 'Suspendu'" dot /></td>
        <td class="font-mono">{{ $user->created_at?->format('d/m/Y') }}</td>
        <td><div class="flex justify-end gap-1.5">
          <a class="btn-icon" href="{{ route('admin.users.edit', $user) }}" title="Modifier">{!! $edit !!}</a>
          @unless ($user->is(auth()->user()))
          <form class="contents" method="POST" action="{{ route('admin.users.toggle-status', $user) }}">
            @csrf
            @method('PATCH')
            <button class="btn-icon" type="submit" title="{{ $user->is_active ? 'Suspendre' : 'Réactiver' }}">{!! $ban !!}</button>
          </form>
          <button class="btn-icon btn-icon-danger" type="button" data-del="{{ $user->name }}" data-action="{{ route('admin.users.destroy', $user) }}" title="Supprimer">{!! $trash !!}</button>
          @endunless
        </div></td>
      </tr>
      @empty
      <tr><td colspan="6" class="text-muted text-center p-7">Aucun utilisateur ne correspond à ces critères.</td></tr>
      @endforelse
    </tbody></table></div>
    <div class="card-footer">
      <span class="text-muted">@if ($users->total())Affichage {{ $users->firstItem() }} à {{ $users->lastItem() }} sur {{ $users->total() }}@else Aucun résultat @endif</span>
      {{ $users->onEachSide(1)->links('partials.pagination') }}
    </div>
  </div>
</section>
@endsection
