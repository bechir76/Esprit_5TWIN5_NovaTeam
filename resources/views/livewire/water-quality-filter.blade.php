{{-- Composant Livewire WaterQualityFilter : le choix d'une zone met à jour la liste sans recharger la page.
     Sans JavaScript, le formulaire GET recharge /qualite-eau?zone=ID.
     A4 : feu tricolore WQRI par zone affiché dans l'en-tête de chaque carte. --}}
<div>
    {{-- A4 : Tableau de bord WQRI public — feux tricolores par zone --}}
    @php
        $zonesWithWqri = $zones->filter(fn ($z) => $z->wqri_score !== null && $z->wqri_level !== null);
    @endphp
    @if ($zonesWithWqri->isNotEmpty() && ! $selectedZone)
    <div class="mb-8">
        <h2 class="text-[1rem] font-semibold mb-4">Indice de risque sanitaire par zone</h2>
        <div class="grid grid-fit-[180px] gap-3">
            @foreach ($zonesWithWqri as $z)
            @php
                $lvl = \App\Enums\WqriLevel::tryFrom($z->wqri_level);
                $light = $lvl?->trafficLight() ?? 'green';
                $lightColors = ['green' => ['bg' => '#d1fae5', 'dot' => '#059669', 'text' => '#065f46'], 'orange' => ['bg' => '#fef3c7', 'dot' => '#d97706', 'text' => '#78350f'], 'red' => ['bg' => '#fee2e2', 'dot' => '#dc2626', 'text' => '#991b1b']];
                $lc = $lightColors[$light] ?? $lightColors['green'];
            @endphp
            <button
                class="rounded-[10px] border border-line p-4 text-left transition-shadow hover:shadow-md focus:outline-none focus:ring-2 focus:ring-water"
                style="background: {{ $lc['bg'] }};"
                wire:click="$set('zone', '{{ $z->id }}')"
                type="button"
                aria-label="Filtrer par {{ $z->name }}"
            >
                <div class="flex items-center gap-2 mb-2">
                    {{-- Feu tricolore --}}
                    <span class="inline-block size-3 rounded-full shrink-0" style="background: {{ $lc['dot'] }};"></span>
                    <span class="text-[.78rem] font-semibold uppercase tracking-wider truncate" style="color: {{ $lc['text'] }};">
                        {{ $lvl?->label() ?? '—' }}
                    </span>
                </div>
                <div class="font-bold text-[.92rem] truncate">{{ $z->name }}</div>
                <div class="font-mono text-[1.5rem] leading-none font-extrabold mt-1" style="color: {{ $lc['dot'] }};">
                    {{ $z->wqri_score }}<span class="text-[.8rem] font-normal opacity-60">/100</span>
                </div>
                @if ($z->wqri_computed_at)
                <div class="text-[.72rem] mt-1.5 opacity-60">Mis à jour {{ \Illuminate\Support\Carbon::parse($z->wqri_computed_at)->diffForHumans() }}</div>
                @endif
            </button>
            @endforeach
        </div>
        <p class="text-[.76rem] text-muted mt-3">
            L'indice WQRI (0–100) agrège la qualité des mesures, l'état des ouvrages et les incidents de contamination sur 90 jours.
            <span class="inline-flex items-center gap-1 ml-1"><span class="inline-block size-2 rounded-full bg-[#059669]"></span> Faible</span>
            <span class="inline-flex items-center gap-1 ml-1"><span class="inline-block size-2 rounded-full bg-[#d97706]"></span> Élevé</span>
            <span class="inline-flex items-center gap-1 ml-1"><span class="inline-block size-2 rounded-full bg-[#dc2626]"></span> Critique</span>
        </p>
    </div>
    @endif

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

        // A4 : WQRI de la zone pour cet ouvrage
        $zoneModel  = $zonesById[$analysisZone?->id ?? 0] ?? null;
        $zoneLvl    = $zoneModel?->wqri_level ? \App\Enums\WqriLevel::tryFrom($zoneModel->wqri_level) : null;
        $zoneScore  = $zoneModel?->wqri_score;
    @endphp

    <div class="card mb-5 overflow-hidden" wire:key="analysis-{{ $analysis->id }}">
      {{-- En-tête de la zone --}}
      <div class="flex flex-wrap items-start justify-between gap-3 border-b border-line bg-surface-2 px-[22px] py-4">
        <div>
          <div class="flex items-center gap-2 mb-[3px]">
            <span class="font-mono text-xs tracking-[.08em] text-muted uppercase">
              {{ $analysisZone->name ?? 'Zone inconnue' }}
            </span>
            {{-- A4 : feu tricolore WQRI de la zone --}}
            @if ($zoneLvl && $zoneScore !== null)
            @php
                $light = $zoneLvl->trafficLight();
                $dotColor = ['green' => '#059669', 'orange' => '#d97706', 'red' => '#dc2626'][$light] ?? '#059669';
                $textColor = ['green' => '#065f46', 'orange' => '#78350f', 'red' => '#991b1b'][$light] ?? '#065f46';
            @endphp
            <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[.72rem] font-semibold"
                  style="background: {{ ['green'=>'#d1fae5','orange'=>'#fef3c7','red'=>'#fee2e2'][$light] ?? '#d1fae5' }}; color: {{ $textColor }};">
                <span class="inline-block size-1.5 rounded-full" style="background: {{ $dotColor }};"></span>
                WQRI {{ $zoneScore }}
            </span>
            @endif
          </div>
          <div class="font-bold text-[1.05rem]">
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

      {{-- Barre de conformité globale + bulletin PDF --}}
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
        {{-- A5 : lien vers le bulletin PDF signé --}}
        <a class="btn btn-ghost btn-sm gap-1.5" href="{{ route('admin.water-analyses.bulletin', $analysis) }}" target="_blank">
          <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="12" y1="18" x2="12" y2="12"/><line x1="9" y1="15" x2="15" y2="15"/></svg>
          Bulletin PDF
        </a>
      </div>
      @endif

      {{-- B4 : explications pédagogiques pour les mesures non conformes --}}
      @php
        $nonCompliantMeasurements = $analysis->measurements->filter(fn($m) => !$m->is_compliant);
      @endphp
      @if ($nonCompliantMeasurements->isNotEmpty())
      <div class="border-t border-line px-[22px] py-4 flex flex-col gap-2.5 bg-crit-soft">
        <div class="text-[.8rem] font-semibold uppercase tracking-wide text-crit-fg mb-1">Que faire ?</div>
        @foreach ($nonCompliantMeasurements as $m)
        @php
          $p = $m->parameter instanceof \App\Enums\WaterParameter
              ? $m->parameter
              : \App\Enums\WaterParameter::from($m->parameter);
          $explanation = app(\App\Services\Ai\WaterQualityAiService::class)->publicExplanationByRules($p, (float) $m->value);
        @endphp
        <p class="text-[.88rem]">{{ $explanation }}</p>
        @endforeach
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
