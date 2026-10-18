@extends('layouts.layout')

@section('title', 'Transparence des dons')

@section('content')
<section class="pt-10 pb-16" data-page="transparence">
  <div class="mx-auto max-w-[1200px] px-5">
    <div class="mb-7 flex flex-col gap-2 [&_p]:max-w-[65ch]"><span class="kicker">Financement participatif</span><h2>Transparence</h2><p class="text-muted">Tous les dons confirmés aux projets publiés. Les citoyens apparaissent par leur prénom et l'initiale de leur nom, ou « Anonyme » s'ils l'ont demandé ; aucune autre donnée personnelle n'est publiée.</p></div>

    <div class="mb-6 grid gap-4 grid-fit-[200px]">
      <div class="card p-[18px]"><span class="caption block">Total collecté</span><strong class="font-mono text-[1.4rem]">{{ \App\Models\Project::money($total) }} DT</strong></div>
      <div class="card p-[18px]"><span class="caption block">Dons confirmés</span><strong class="font-mono text-[1.4rem]">{{ number_format($count, 0, ',', ' ') }}</strong></div>
      @foreach (\App\Enums\FundingSource::cases() as $source)
      <div class="card p-[18px]"><span class="caption block">{{ $source->label() }}</span><strong class="font-mono text-[1.1rem]">{{ \App\Models\Project::money($bySource[$source->value] ?? 0) }} DT</strong></div>
      @endforeach
    </div>

    <div class="card">
      <div class="overflow-x-auto"><table class="data-table"><thead><tr><th>Date</th><th>Projet</th><th>Donateur</th><th>Source</th><th class="text-right">Montant</th></tr></thead><tbody>
        @forelse ($fundings as $funding)
        <tr>
          <td class="font-mono whitespace-nowrap">{{ ($funding->paid_at ?? $funding->funded_at)->format('d/m/Y') }}</td>
          <td><a class="hover:underline" href="{{ route('projects.show', $funding->project) }}">{{ $funding->project->title }}</a></td>
          <td>{{ $funding->publicDonorName() }}@if ($funding->is_matching) <span class="badge b-info">cofinancement</span>@endif</td>
          <td>{{ $funding->source_type->label() }}</td>
          <td class="font-mono text-right whitespace-nowrap">{{ \App\Models\Project::money($funding->amount) }} DT</td>
        </tr>
        @empty
        <tr><td colspan="5" class="text-muted text-center p-7">Aucun don confirmé pour le moment.</td></tr>
        @endforelse
      </tbody></table></div>
      <div class="card-footer"><span class="text-muted">{{ $fundings->total() }} don(s)</span>{{ $fundings->onEachSide(1)->links('partials.pagination') }}</div>
    </div>
  </div>
</section>
@endsection
