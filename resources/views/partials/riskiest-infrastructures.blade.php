{{-- Les 5 ouvrages les plus à risque (score prédictif RiskScorer) : page Analyses IA et tableau de bord. --}}
<div class="overflow-x-auto"><table class="data-table min-w-[480px]"><thead><tr><th>Infrastructure</th><th>Facteurs principaux</th><th class="w-[38%]">Risque</th></tr></thead><tbody>
  @forelse ($riskiest as $infrastructure)
  <tr>
    <td><a class="font-semibold" href="{{ route('admin.infrastructures.show', $infrastructure) }}">{{ $infrastructure->name }}</a><br><span class="font-mono text-[.76rem] text-muted">{{ $infrastructure->code }} · {{ $infrastructure->zone->name }}</span></td>
    <td class="text-[.84rem] text-muted">{{ collect($infrastructure->risk_factors ?? [])->sortByDesc('points')->filter(fn ($f) => $f['points'] > 0)->take(2)->pluck('label')->map(fn ($l) => \Illuminate\Support\Str::ucfirst($l))->implode(' · ') }}</td>
    <td><div class="flex items-center gap-2.5"><div class="h-2 min-w-20 flex-1 overflow-hidden rounded-full bg-surface-2"><span class="block h-full rounded-full" style="width:{{ $infrastructure->risk_score }}%;background:{{ $infrastructure->risk_level->color() }}"></span></div><span class="w-[38px] text-right font-mono">{{ $infrastructure->risk_score }}</span></div></td>
  </tr>
  @empty
  <tr><td colspan="3" class="p-7 text-center text-muted">Aucun ouvrage évalué : lancez <span class="font-mono">php artisan infrastructures:score</span>.</td></tr>
  @endforelse
</tbody></table></div>
