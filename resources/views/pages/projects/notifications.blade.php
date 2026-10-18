@extends('layouts.layout')

@section('title', 'Mes notifications')

@section('content')
<section class="pt-10 pb-16" data-page="notifications">
  <div class="mx-auto max-w-[900px] px-5">
    <div class="mb-7 flex flex-wrap items-end justify-between gap-4"><div class="flex flex-col gap-2"><span class="kicker">Espace citoyen</span><h2>Mes notifications</h2></div>
      @if (auth()->user()->unreadNotifications()->exists())
      <form method="POST" action="{{ route('notifications.read') }}">
        @csrf
        <button class="btn btn-ghost btn-sm" type="submit">Tout marquer comme lu</button>
      </form>
      @endif
    </div>
    <div class="card p-[22px]">
      @forelse ($notifications as $notification)
      <div @class(['line-item flex-col items-start! gap-1', 'border-b-0' => $loop->last])>
        <div class="flex w-full flex-wrap items-center justify-between gap-2"><strong>{{ $notification->data['title'] ?? 'Notification' }}</strong>@if ($notification->unread())<span class="badge b-info">nouveau</span>@endif</div>
        <p class="text-[.9rem]">{{ $notification->data['message'] ?? '' }}</p>
        <div class="flex flex-wrap gap-3 text-[.8rem] text-muted"><span>{{ $notification->created_at->diffForHumans() }}</span>@if (! empty($notification->data['url']))<a class="text-water underline" href="{{ $notification->data['url'] }}">Voir le détail</a>@endif</div>
      </div>
      @empty
      <p class="text-muted">Aucune notification. Vous serez prévenu ici quand vos dons seront confirmés et quand les projets soutenus avanceront.</p>
      @endforelse
    </div>
    <div class="mt-4 flex justify-end">{{ $notifications->links('partials.pagination') }}</div>
  </div>
</section>
@endsection
