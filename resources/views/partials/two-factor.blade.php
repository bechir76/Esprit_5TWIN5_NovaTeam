{{-- Authentification à deux facteurs (TOTP) : bloc partagé par le profil citoyen et le profil du personnel --}}
<div class="card flex scroll-mt-24 flex-col gap-4 p-[22px]" id="deux-facteurs">
  <div class="flex flex-wrap items-center justify-between gap-3">
    <h3>Authentification à deux facteurs</h3>
    @if ($user->hasTwoFactorEnabled())
    <span class="badge b-ok">Activée</span>
    @elseif ($user->two_factor_secret)
    <span class="badge b-warn">À confirmer</span>
    @else
    <span class="badge b-mute">Désactivée</span>
    @endif
  </div>
  <p class="text-[.9rem] text-muted">À la connexion, un code à 6 chiffres généré par une application d'authentification (Google Authenticator, Microsoft Authenticator, Authy…) est demandé en plus du mot de passe.</p>

  @if ($user->hasTwoFactorEnabled())
    {{-- Codes de récupération : affichés une seule fois, juste après leur génération --}}
    @if (session('show_recovery_codes'))
    <div class="callout callout-info flex-col">
      <strong>Codes de récupération</strong>
      <span>Conservez-les en lieu sûr : chaque code permet une seule connexion si vous n'avez plus accès à votre application.</span>
      <div class="grid grid-cols-2 gap-1.5 font-mono">@foreach ($user->two_factor_recovery_codes as $code)<span>{{ $code }}</span>@endforeach</div>
    </div>
    @else
    <p class="text-[.86rem]">{{ count($user->two_factor_recovery_codes ?? []) }} code(s) de récupération restant(s).</p>
    @endif
    <form method="POST" action="{{ route('two-factor.recovery-codes') }}">
      @csrf
      <button class="btn btn-ghost" type="submit">Générer de nouveaux codes de récupération</button>
    </form>
    <form class="flex flex-wrap items-end gap-3" method="POST" action="{{ route('two-factor.disable') }}" novalidate>
      @csrf
      @method('DELETE')
      <div class="flex min-w-[220px] flex-1 flex-col gap-1.5">
        <label class="text-[.86rem] font-semibold" for="two_factor_password">Mot de passe actuel</label>
        <input @class(['input', 'border-crit! bg-crit-soft!' => $errors->twoFactor->has('password')]) type="password" id="two_factor_password" name="password" autocomplete="current-password">
      </div>
      <button class="btn bg-crit text-white" type="submit">Désactiver la 2FA</button>
    </form>
    @if ($errors->twoFactor->has('password'))<span class="text-[.8rem] font-medium text-crit-fg">{{ $errors->twoFactor->first('password') }}</span>@endif
  @elseif ($user->two_factor_secret)
    {{-- Activation en cours : QR code à scanner puis premier code pour confirmer --}}
    <div class="flex flex-wrap items-start gap-5">
      <div class="rounded-lg bg-white p-2" aria-label="QR code à scanner">{!! $user->twoFactorQrCodeSvg() !!}</div>
      <div class="flex min-w-[220px] flex-1 flex-col gap-3">
        <p class="text-[.9rem]">1. Scannez ce QR code avec votre application, ou saisissez la clé <span class="font-mono break-all">{{ $user->two_factor_secret }}</span>.</p>
        <form class="flex flex-col gap-3" method="POST" action="{{ route('two-factor.confirm') }}" novalidate>
          @csrf
          <x-form.input name="code" label="2. Code à 6 chiffres affiché par l'application" inputmode="numeric" autocomplete="one-time-code" maxlength="6" />
          <div><button class="btn btn-primary" type="submit">Confirmer l'activation</button></div>
        </form>
      </div>
    </div>
  @else
    <form method="POST" action="{{ route('two-factor.enable') }}">
      @csrf
      <button class="btn btn-navy" type="submit">Activer la 2FA</button>
    </form>
  @endif
</div>
