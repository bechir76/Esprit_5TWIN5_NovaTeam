{{-- Reçu PDF d'un don (dompdf) : fragment sans <html>/<body>, dompdf ajoute le document ; styles inline (pas de Vite) --}}
<style>
  * { font-family: DejaVu Sans, sans-serif; }
  body { color: #0B2233; font-size: 12px; }
  h1 { font-size: 20px; color: #0E4A72; margin: 0 0 4px; }
  .muted { color: #5B7487; }
  table { width: 100%; border-collapse: collapse; margin: 18px 0; }
  td { padding: 8px 6px; border-bottom: 1px solid #DCE6EE; }
  td.label { color: #5B7487; width: 38%; }
  .amount { font-size: 22px; font-weight: bold; color: #0E4A72; }
  .footer { margin-top: 24px; font-size: 10px; }
</style>

<h1>AquaSecure · Reçu de don</h1>
<div class="muted">Plateforme citoyenne de financement des projets de rénovation du réseau d'eau du Grand Tunis</div>

<table>
  <tr><td class="label">Numéro de reçu</td><td><strong>{{ $funding->receipt_number }}</strong></td></tr>
  <tr><td class="label">Donateur</td><td>{{ $funding->donor_name }}</td></tr>
  <tr><td class="label">Projet soutenu</td><td>{{ $funding->project->title }}</td></tr>
  <tr><td class="label">Date du paiement</td><td>{{ ($funding->paid_at ?? $funding->funded_at)->format('d/m/Y à H:i') }}</td></tr>
  <tr><td class="label">Référence du don</td><td>{{ $funding->reference }}</td></tr>
  <tr><td class="label">Montant</td><td class="amount">{{ \App\Models\Project::money($funding->amount) }} DT</td></tr>
  @if ($funding->status === \App\Enums\FundingStatus::Refunded)
  <tr><td class="label">Statut</td><td>Remboursé (collecte non aboutie)</td></tr>
  @endif
</table>

<table>
  <tr>
    <td style="width:170px;border:0"><img src="{{ $qrDataUri }}" width="150" height="150" alt="QR code de vérification"></td>
    <td style="border:0" class="muted">Scannez ce QR code pour vérifier l'authenticité du reçu sur AquaSecure.<br><br><span style="font-size:9px">{{ $verifyUrl }}</span></td>
  </tr>
</table>

<div class="footer muted">Paiement traité par Stripe. AquaSecure ne conserve aucune donnée de carte bancaire. Reçu généré le {{ now()->format('d/m/Y') }}.</div>
