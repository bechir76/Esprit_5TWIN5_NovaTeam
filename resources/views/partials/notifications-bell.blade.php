{{-- Cloche des notifications (console et site citoyen) : dernières notifications de la table partagée
     « notifications » (format title / message / url), lien vers le centre de notifications. --}}
@auth
@php
    $bellUser = auth()->user();
    $unread = $bellUser->unreadNotifications()->count();
    $latest = $bellUser->notifications()->latest()->limit(6)->get();
@endphp
<div class="relative" x-data="{ open: false }" @click.outside="open = false" @keydown.escape.window="open = false">
  <button class="relative grid size-[38px] place-items-center rounded-[9px] border border-line bg-surface text-ink" type="button" @click="open = ! open" :aria-expanded="open" aria-label="Notifications{{ $unread ? ' ('.$unread.' non lues)' : '' }}">
    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M6 8a6 6 0 1 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.9 1.9 0 0 0 3.4 0"/></svg>
    @if ($unread)
    <span class="absolute -top-1 -right-1 grid min-w-[18px] place-items-center rounded-full bg-crit px-1 text-[.66rem] font-bold text-white">{{ $unread > 9 ? '9+' : $unread }}</span>
    @endif
  </button>
  <div class="absolute top-[50px] right-0 z-30 w-[340px] max-w-[calc(100vw-32px)] rounded-card border border-line bg-surface p-1.5 shadow-drop" x-show="open" x-cloak x-transition.opacity>
    <div class="flex items-center justify-between px-3 py-2"><strong>Notifications</strong>@if ($unread)<span class="badge b-info">{{ $unread }} non lue(s)</span>@endif</div>
    @forelse ($latest as $notification)
    <a class="flex gap-2.5 rounded-md px-3 py-2.5 text-[.85rem] hover:bg-surface-2" href="{{ $notification->data['url'] ?? route('notifications.index') }}">
      <span @class(['badge dot self-start', 'b-info' => $notification->read_at === null, 'b-mute' => $notification->read_at !== null])></span>
      <div><strong>{{ $notification->data['title'] ?? 'Notification' }}</strong> {{ \Illuminate\Support\Str::limit($notification->data['message'] ?? '', 90) }}<div class="text-muted">{{ $notification->created_at->diffForHumans() }}</div></div>
    </a>
    @empty
    <p class="px-3 py-2.5 text-[.85rem] text-muted">Aucune notification.</p>
    @endforelse
    <a class="menu-item justify-center" href="{{ route('notifications.index') }}">Toutes les notifications</a>
  </div>
</div>
@endauth
