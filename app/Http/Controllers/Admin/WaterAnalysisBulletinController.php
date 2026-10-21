<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\WaterAnalysis;
use App\Services\Ai\WaterQualityAiService;
use App\Services\WaterQuality\WaterAnalysisImporter;
use App\Exceptions\ImportRolledBack;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * A5 : Bulletin d'analyse PDF signé avec QR code.
 * A6 : Import CSV multi-paramètres.
 */
class WaterAnalysisBulletinController extends Controller
{
    // ---------------------------------------------------------------- A5 : bulletin PDF

    /**
     * Génère le bulletin PDF d'une analyse avec :
     * - Informations de l'analyse et de l'infrastructure
     * - Tableau des mesures avec indicateurs de conformité
     * - Courbes d'évolution des paramètres sur 30 jours (SVG pré-calculé)
     * - QR code pointant vers l'URL de vérification signée
     */
    public function download(WaterAnalysis $analysis, WaterQualityAiService $ai): Response
    {
        Gate::authorize('view', $analysis);

        $analysis->load(['infrastructure.zone', 'sampledBy', 'measurements']);

        // Historique 30 jours par paramètre pour les sparklines
        $history = $this->parameterHistory($analysis);

        // QR code de vérification d'authenticité (URL signée)
        $verifyUrl = URL::signedRoute('admin.water-analyses.bulletin.verify', ['analysis' => $analysis]);
        $qr = (new Writer(new ImageRenderer(new RendererStyle(160, 1), new SvgImageBackEnd)))->writeString($verifyUrl);

        // Prédiction de dégradation IA (B2) — utilise le cache si récent
        $degradationPrediction = $analysis->ai_degradation_prediction
            ?? $ai->predictDegradation($analysis->infrastructure);

        return Pdf::loadView('pages.water-analyses.bulletin-pdf', [
            'analysis' => $analysis,
            'history' => $history,
            'verifyUrl' => $verifyUrl,
            'qrDataUri' => 'data:image/svg+xml;base64,'.base64_encode($qr),
            'degradationPrediction' => $degradationPrediction,
            'generatedAt' => now(),
        ])->download("bulletin-{$analysis->reference}.pdf");
    }

    /** GET /admin/analyses/{id}/bulletin/verifier?signature=… : vérification publique de l'authenticité. */
    public function verify(WaterAnalysis $analysis): View
    {
        $analysis->load(['infrastructure.zone', 'measurements']);

        return view('pages.water-analyses.bulletin-verify', ['analysis' => $analysis]);
    }

    // ---------------------------------------------------------------- A6 : import CSV

    public function importCreate(): View
    {
        Gate::authorize('create', WaterAnalysis::class);

        return view('pages.water-analyses.import');
    }

    public function importStore(Request $request, WaterAnalysisImporter $importer): RedirectResponse
    {
        Gate::authorize('create', WaterAnalysis::class);

        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:2048'],
            'laboratory' => ['nullable', 'string', 'max:150'],
        ]);

        $handle = fopen($request->file('file')->getRealPath(), 'r');

        try {
            $result = $importer->import($handle, $request->input('laboratory'));
        } catch (ImportRolledBack $e) {
            return back()->withErrors(['file' => $e->getMessage()]);
        } finally {
            fclose($handle);
        }

        $message = "{$result['imported']} analyse(s) importée(s)";
        if ($result['skipped'] > 0) {
            $message .= ", {$result['skipped']} ligne(s) ignorée(s)";
        }
        $message .= '.';

        $redirect = to_route('admin.water-analyses.index')->with('status', $message);

        if (! empty($result['errors'])) {
            // Les erreurs non bloquantes sont transmises en session pour affichage
            $redirect = $redirect->with('import_warnings', $result['errors']);
        }

        return $redirect;
    }

    /** Télécharge le modèle CSV pré-rempli avec les en-têtes attendus. */
    public function importTemplate(): Response
    {
        Gate::authorize('create', WaterAnalysis::class);

        $headers = ['infrastructure_code', 'sampled_at', 'sampling_point', 'laboratory', 'chlore', 'ph', 'turbidite', 'nitrate', 'conductivite', 'ecoli', 'notes'];
        $example = ['RES-ARI-001', now()->format('Y-m-d H:i'), 'Sortie réservoir', 'Laboratoire X', '0.25', '7.2', '0.4', '12', '450', '0', ''];

        $csv = implode(';', $headers)."\n".implode(';', $example)."\n";

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="modele-import-analyses.csv"',
        ]);
    }

    // ---------------------------------------------------------------- B3 : résumé exécutif IA

    public function executiveSummary(Request $request, WaterQualityAiService $ai): View
    {
        Gate::authorize('viewAny', WaterAnalysis::class);

        $validated = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        $from = isset($validated['from']) ? \Illuminate\Support\Carbon::parse($validated['from'])->startOfDay() : now()->startOfMonth();
        $to = isset($validated['to']) ? \Illuminate\Support\Carbon::parse($validated['to'])->endOfDay() : now()->endOfDay();

        $summary = null;
        if ($request->isMethod('get') && ($request->has('from') || $request->has('to') || ! $request->has('reset'))) {
            $summary = $ai->executiveSummary($from, $to);
        }

        return view('pages.water-analyses.executive-summary', [
            'from' => $from,
            'to' => $to,
            'summary' => $summary,
        ]);
    }

    // ---------------------------------------------------------------- Helpers

    /**
     * Historique des 30 derniers jours pour chaque paramètre de l'analyse (pour les sparklines PDF).
     *
     * @return array<string, list<array{date: string, value: float, compliant: bool}>>
     */
    private function parameterHistory(WaterAnalysis $analysis): array
    {
        $since = now()->subDays(30);
        $infraId = $analysis->infrastructure_id;

        $rows = \App\Models\Measurement::query()
            ->join('water_analyses', 'measurements.water_analysis_id', '=', 'water_analyses.id')
            ->where('water_analyses.infrastructure_id', $infraId)
            ->where('water_analyses.sampled_at', '>=', $since)
            ->whereIn('measurements.parameter', $analysis->measurements->pluck('parameter')->toArray())
            ->orderBy('water_analyses.sampled_at')
            ->select(['measurements.parameter', 'measurements.value', 'measurements.is_compliant', 'water_analyses.sampled_at'])
            ->get();

        return $rows->groupBy('parameter')->map(fn ($group) => $group->map(fn ($r) => [
            'date' => \Illuminate\Support\Carbon::parse($r->sampled_at)->format('d/m'),
            'value' => round((float) $r->value, 3),
            'compliant' => (bool) $r->is_compliant,
        ])->values()->all())->all();
    }
}
