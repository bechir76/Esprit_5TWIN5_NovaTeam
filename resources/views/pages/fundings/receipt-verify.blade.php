@extends('layouts.layout')

@section('title', 'Vérification du reçu')

@section('content')
<section class="pt-10 pb-16" data-page="recu-verifie">
  <div class="mx-auto max-w-[640px] px-5">
    {{-- URL signée (QR code du reçu) : aucune donnée personnelle affichée --}}
    <div class="card flex flex-col gap-3 p-[22px]">
      <span class="badge b-ok self-start">✓ Reçu authentique</span>
      <h2>Reçu authentique : {{ \App\Models\Project::money($funding->amount) }} DT pour le projet {{ $funding->project->title }} le {{ ($funding->paid_at ?? $funding->funded_at)->format('d/m') }}</h2>
      <div class="line-item"><span>Numéro de reçu</span><span class="font-mono">{{ $funding->receipt_number }}</span></div>
      <div class="line-item"><span>Date</span><span class="font-mono">{{ ($funding->paid_at ?? $funding->funded_at)->format('d/m/Y') }}</span></div>
      <div class="line-item border-b-0"><span>Statut</span><span>{{ $funding->status->label() }}</span></div>
      @if ($funding->project->isPublished())
      <a class="btn btn-ghost justify-center" href="{{ route('projects.show', $funding->project) }}">Voir le projet</a>
      @endif
    </div>
  </div>
</section>
@endsection
