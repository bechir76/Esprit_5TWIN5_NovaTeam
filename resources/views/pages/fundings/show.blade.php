@extends('layouts.layout')

@section('title', 'Financement '.$funding->reference)

@section('content')
<section data-page="financement" class="flex flex-col gap-3">
  <x-page-header :title="'Financement '.$funding->reference" :crumb="'Console / Financements / '.$funding->reference">
    <x-slot:badges><x-status-badge :value="$funding->source_type->label()" :variant="$funding->source_type->badge()" /><x-status-badge :value="$funding->status->label()" :variant="$funding->status->badge()" /></x-slot:badges>
    <div class="flex flex-wrap items-center gap-2.5">
      @if ($funding->hasReceipt() && auth()->user()->can('downloadReceipt', $funding))
      <a class="btn btn-ghost" href="{{ route('fundings.receipt', $funding) }}">Reçu PDF</a>
      @endif
      <a class="btn btn-ghost" href="{{ route('admin.fundings.edit', $funding) }}">Modifier</a>
      @unless ($funding->stripe_payment_intent_id)
      <button class="btn btn-danger" type="button" data-del="{{ $funding->reference }}" data-action="{{ route('admin.fundings.destroy', $funding) }}">Supprimer</button>
      @endunless
    </div>
  </x-page-header>
  <div class="grid items-start gap-[18px] min-[1101px]:grid-cols-[minmax(0,1fr)_340px]">
    <div class="card p-[18px] flex flex-col gap-4">
      <div class="grid grid-fit-[170px] gap-4">
        <div><span class="caption block">Référence</span><span class="font-mono">{{ $funding->reference }}</span></div>
        <div><span class="caption block">Montant</span><span class="font-mono">{{ \App\Models\Project::money($funding->amount) }} DT</span></div>
        <div><span class="caption block">Date</span><span class="font-mono">{{ $funding->funded_at->format('d/m/Y') }}</span></div>
        <div><span class="caption block">Source</span><span>{{ $funding->source_type->label() }}</span></div>
        <div><span class="caption block">Financeur</span><span>{{ $funding->donor_name }}@if ($funding->is_anonymous) <span class="text-muted">(affiché « Anonyme »)</span>@endif</span></div>
        <div><span class="caption block">Compte citoyen</span><span>{{ $funding->user ? $funding->user->name.' · '.$funding->user->email : '—' }}</span></div>
        <div><span class="caption block">Statut</span><span>{{ $funding->status->label() }}</span></div>
        <div><span class="caption block">Encaissé le</span><span class="font-mono">{{ $funding->paid_at?->format('d/m/Y H:i') ?? '—' }}</span></div>
        <div><span class="caption block">Reçu</span><span class="font-mono">{{ $funding->receipt_number ?? '—' }}</span></div>
        <div><span class="caption block">Enregistré le</span><span class="font-mono">{{ $funding->created_at->format('d/m/Y H:i') }}</span></div>
        @if ($funding->stripe_session_id)
        <div><span class="caption block">Paiement Stripe</span><span class="font-mono text-[.74rem] break-all">{{ $funding->stripe_payment_intent_id ?? $funding->stripe_session_id }}</span></div>
        @endif
        @if ($funding->stripe_refund_id)
        <div><span class="caption block">Remboursement Stripe</span><span class="font-mono text-[.74rem] break-all">{{ $funding->stripe_refund_id }}</span></div>
        @endif
      </div>
      @if ($funding->is_matching)
      <div class="callout bg-water-soft">Cofinancement municipal automatique (1 pour 1) d'un don citoyen.</div>
      @elseif ($funding->matching)
      <div class="callout bg-water-soft">Ce don a été doublé par le cofinancement <a class="underline" href="{{ route('admin.fundings.show', $funding->matching) }}">{{ $funding->matching->reference }}</a> ({{ \App\Models\Project::money($funding->matching->amount) }} DT).</div>
      @endif
      <div><span class="caption block">Note</span><p class="mt-1.5 max-w-[70ch]">{{ $funding->note ?: '—' }}</p></div>
    </div>
    <aside class="flex flex-col gap-[18px]">
      <div class="card p-[18px] flex flex-col gap-3"><h2>Projet</h2>
        <x-project-progress :project="$funding->project" />
        <div class="grid grid-fit-[170px] gap-2">
          <div><span class="caption block">Statut</span><x-status-badge :value="$funding->project->status->label()" :variant="$funding->project->status->badge()" /></div>
          <div><span class="caption block">Infrastructure</span><span>{{ $funding->project->infrastructure ? $funding->project->infrastructure->code.' · '.$funding->project->infrastructure->zone->name : 'Multi-sites' }}</span></div>
        </div>
        <a class="btn btn-ghost justify-center mt-1.5" href="{{ route('admin.projects.show', $funding->project) }}">Voir le projet</a>
      </div>
    </aside>
  </div>
</section>
@endsection
