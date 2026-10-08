{{-- Composant Livewire WaterQualityFilter : le choix d'une zone met à jour la liste sans recharger la page.
     Sans JavaScript, le formulaire GET recharge /qualite-eau?zone=ID. --}}
<div>
    {{-- Filtre par zone --}}
    <form class="mb-7" method="GET" action="{{ route('water-quality') }}" x-on:submit.prevent>
      <div class="flex flex-wrap items-center gap-2.5">
        <label class="text-[.9rem] font-semibold" for="zoneFilter">Filtrer par zone&nbsp;:</label>
        <select class="input w-auto min-w-[200px]" id="zoneFilter" name="zone" wire:model.live="zone" aria-label="Choisir une zone">
          <option value="">Toutes les zones</option>
          @foreach ($zones as $option)
          <option value="{{ $option->id }}">{{ $option->name }}</option>
          @endforeach
        </select>
        <noscript><button class="btn btn-ghost btn-sm" type="submit">Filtrer</button></noscript>
        @if ($selectedZone)
        <button class="btn btn-ghost btn-sm" type="button" wire:click="resetZone">Réinitialiser</button>
        @endif
        <span class="text-[.8rem] text-muted" wire:loading.delay>Chargement…</span>
      </div>
    </form>

    <div class="transition-opacity" wire:loading.class="opacity-60">
    @forelse ($lastByZone as $analysis)
    @php
      $analysisZone = $analysis->infrastructure->zone;
      $rate    = $analysis->compliance_rate;
      $byParam = $analysis->measurements->keyBy(
          fn ($m) => $m->parameter instanceof \App\Enums\WaterParameter
              ? $m->parameter->value
              : $m->parameter
      );
      $allOk = $rate === 100;
    @endphp

    <div class="card mb-5 overflow-hidden" wire:key="analysis-{{ $analysis->id }}">
      {{-- En-tête de la zone --}}
      <div class="flex flex-wrap items-start justify-between gap-3 border-b border-line bg-surface-2 px-[22px] py-4">
        <div>
          <div class="font-mono text-xs tracking-[.08em] text-muted uppercase">
            {{ $analysisZone->name ?? 'Zone inconnue' }}
          </div>
          <div class="font-bold text-[1.05rem] mt-[3px]">
            {{ $analysis->infrastructure->name }}
            <span class="font-normal text-muted text-[.88rem]">· {{ $analysis->infrastructure->type->label() }}</span>
          </div>
          <div class="text-[.82rem] text-muted mt-1">
            Prélèvement du {{ $analysis->sampled_at->format('d/m/Y H:i') }}
            @if ($analysis->sampling_point) · {{ $analysis->sampling_point }} @endif
            @if ($analysis->laboratory) · {{ $analysis->laboratory }} @endif
          </div>
        </div>
        <div class="flex gap-2.5 items-center flex-wrap">
          @if ($rate !== null)
          <div class="text-right">
            <div @class(['font-mono text-[1.4rem] leading-none font-extrabold', 'text-teal' => $allOk, 'text-crit' => ! $allOk])>
              {{ $rate }}&thinsp;%
            </div>
            <div class="text-[.76rem] text-muted">conformité</div>
          </div>
          @endif
          @if ($allOk)
            <span class="badge b-ok">✓ Conforme</span>
          @else
            <span class="badge b-crit">✕ Non conforme</span>
          @endif
        </div>
      </div>

      {{-- Grille des 4 paramètres principaux --}}
      <div class="grid grid-fit-[200px] border-b border-line">
        @foreach ($mainParams as $param)
        @php
          $m        = $byParam->get($param->value);
          $compliant = $m ? $m->is_compliant : null;
          $valStr   = $m ? rtrim(rtrim(number_format((float) $m->value, 4, ',', ''), '0'), ',') : null;
          $bgClass  = $compliant === true ? 'bg-ok-soft' : ($compliant === false ? 'bg-crit-soft' : 'bg-surface');
          $fgClass  = $compliant === true ? 'text-ok-fg' : ($compliant === false ? 'text-crit-fg' : 'text-muted');
        @endphp
        <div class="border-r border-line px-5 py-[18px] {{ $bgClass }}">
          <div class="mb-1.5 text-[.78rem] font-semibold tracking-[.06em] text-muted uppercase">
            {{ $param->label() }}
          </div>
          @if ($valStr !== null)
          <div class="font-mono text-[1.3rem] leading-none font-bold {{ $fgClass }}">
            {{ $valStr }}@if ($param->unit()) <small class="text-[.75rem] font-normal"> {{ $param->unit() }}</small>@endif
          </div>
          <div class="text-[.76rem] text-muted mt-1">{{ $param->thresholdHint() }}</div>
          <div class="mt-2">
            @if ($compliant)
              <span class="badge b-ok">Conforme</span>
            @else
              <span class="badge b-crit">Non conforme</span>
            @endif
          </div>
          @else
          <div class="text-muted text-[.9rem]">Non mesuré</div>
          @endif
        </div>
        @endforeach
      </div>

      {{-- Barre de conformité globale + lien --}}
      @if ($rate !== null)
      <div class="flex flex-wrap items-center gap-3.5 px-[22px] py-3.5">
        <div class="flex-1 min-w-[120px]">
          <div class="h-1.5 overflow-hidden rounded-[3px] bg-line">
            <div @class(['h-full rounded-[3px]', 'bg-teal' => $allOk, 'bg-crit' => ! $allOk]) style="width:{{ $rate }}%"></div>
          </div>
        </div>
        <span class="text-[.82rem] text-muted whitespace-nowrap">
          {{ $analysis->measurements->where('is_compliant', true)->count() }}
          / {{ $analysis->measurements->count() }} paramètres conformes
        </span>
      </div>
      @endif
    </div>

    @empty
    <div class="text-center p-12 text-muted">
      <p class="text-[1.1rem] mb-2">Aucune analyse validée disponible.</p>
      <p>Les résultats apparaissent dès qu'une analyse est validée par le laboratoire.</p>
    </div>
    @endforelse
    </div>
</div>
