<?php

namespace App\Http\Controllers\Admin;

use App\Enums\InfrastructureStatus;
use App\Enums\InfrastructureType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreInfrastructureRequest;
use App\Http\Requests\Admin\UpdateInfrastructureRequest;
use App\Models\Infrastructure;
use App\Models\Zone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** CRUD des infrastructures (personnel). */
class InfrastructureController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'type' => ['nullable', Rule::enum(InfrastructureType::class)],
            'status' => ['nullable', Rule::enum(InfrastructureStatus::class)],
            'zone' => ['nullable', 'integer'],
        ]);

        $infrastructures = Infrastructure::query()
            ->with('zone')
            ->search($filters['q'] ?? null)
            ->ofType($filters['type'] ?? null)
            ->ofStatus($filters['status'] ?? null)
            ->inZone($filters['zone'] ?? null)
            ->orderBy('code')
            ->paginate(10)
            ->withQueryString();

        return view('back.infrastructures.index', [
            'infrastructures' => $infrastructures,
            'filters' => $filters,
            'zones' => Zone::options(),
        ]);
    }

    public function create(Request $request): View
    {
        // ?zone=ID (lien « Ajouter » depuis la fiche d'une zone) : zone présélectionnée
        $infrastructure = new Infrastructure([
            'zone_id' => $request->integer('zone') ?: null,
            'is_public' => true,
        ]);

        return view('back.infrastructures.form', ['infrastructure' => $infrastructure, 'zones' => Zone::options()]);
    }

    public function store(StoreInfrastructureRequest $request): RedirectResponse
    {
        $infrastructure = Infrastructure::create($request->validated());

        return to_route('admin.infrastructures.show', $infrastructure)->with('status', "Infrastructure {$infrastructure->code} créée.");
    }

    public function show(Infrastructure $infrastructure): View
    {
        $infrastructure->load('zone');

        return view('back.infrastructures.show', compact('infrastructure'));
    }

    public function edit(Infrastructure $infrastructure): View
    {
        return view('back.infrastructures.form', ['infrastructure' => $infrastructure, 'zones' => Zone::options()]);
    }

    public function update(UpdateInfrastructureRequest $request, Infrastructure $infrastructure): RedirectResponse
    {
        $infrastructure->update($request->validated());

        return to_route('admin.infrastructures.show', $infrastructure)->with('status', "Infrastructure {$infrastructure->code} mise à jour.");
    }

    public function destroy(Infrastructure $infrastructure): RedirectResponse
    {
        $infrastructure->delete();

        return to_route('admin.infrastructures.index')->with('status', "Infrastructure {$infrastructure->code} supprimée.");
    }
}
