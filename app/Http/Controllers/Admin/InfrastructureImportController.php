<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ImportInfrastructuresRequest;
use App\Models\Infrastructure;
use App\Models\Zone;
use App\Services\Infrastructures\InfrastructureImporter;
use App\Support\Csv;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** A9 : import CSV des infrastructures (formulaire, rapport par ligne, fichier modèle). InfrastructurePolicy::import. */
class InfrastructureImportController extends Controller
{
    public function create(): View
    {
        Gate::authorize('import', Infrastructure::class);

        return view('pages.infrastructures.import', [
            'report' => session('import_report'),
            'columns' => array_keys(InfrastructureImporter::COLUMNS),
            'required' => InfrastructureImporter::REQUIRED_COLUMNS,
        ]);
    }

    public function store(ImportInfrastructuresRequest $request, InfrastructureImporter $importer): RedirectResponse
    {
        $report = $importer->import($request->file('file')->getRealPath(), $request->boolean('strict'));

        $imported = count($report['imported']);
        $errors = count($report['errors']);
        $message = $report['rolled_back']
            ? "Import annulé (tout ou rien) : {$errors} ligne(s) en erreur, aucune infrastructure créée."
            : "{$imported} infrastructure(s) importée(s)".($errors ? ", {$errors} ligne(s) en erreur." : '.');

        return to_route('admin.infrastructures.import')
            ->with('import_report', $report)
            ->with($imported && ! $errors ? 'status' : 'error', $message);
    }

    /** Fichier modèle : en-tête et deux lignes d'exemple (séparateur ;, UTF-8 avec BOM pour Excel). */
    public function template(): StreamedResponse
    {
        Gate::authorize('import', Infrastructure::class);

        $zone = Zone::query()->orderBy('name')->value('name') ?? 'Ariana Nord';

        return response()->streamDownload(function () use ($zone): void {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            // Le nom de la zone est saisi par le personnel : cellules neutralisées contre l'injection de formule
            Csv::row($out, array_keys(InfrastructureImporter::COLUMNS));
            Csv::row($out, ['RES-ARI-901', 'Réservoir El Menzah 9', 'Réservoir', $zone, 'Béton armé', '6000', 'm³', '1995', '4,5', 'À surveiller', '15/03/2026', '36.8430', '10.1715', 'oui', 'Fissures relevées sur le radier.']);
            Csv::row($out, ['CND-ARI-902', 'Conduite DN 300 Ennasr', 'conduite', $zone, 'Fonte ductile', '4,2', 'km', '2008', '', 'bon', '', '', '', 'non', '']);
            fclose($out);
        }, 'modele-import-infrastructures.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
