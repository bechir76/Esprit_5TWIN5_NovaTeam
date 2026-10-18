<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreUserRequest;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** CRUD des utilisateurs (Gate « manage-users » : administrateurs uniquement, appliquée sur les routes). */
class UserController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'role' => ['nullable', Rule::enum(Role::class)],
            'status' => ['nullable', Rule::in(['active', 'suspended'])],
        ]);

        $users = User::query()
            ->search($filters['q'] ?? null)
            ->when($filters['role'] ?? null, fn ($query, $role) => $query->where('role', $role))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('is_active', $status === 'active'))
            ->latest()
            ->orderByDesc('id')
            ->paginate(10)
            ->withQueryString();

        $counts = User::query()
            ->selectRaw('count(*) as total')
            ->selectRaw('sum(case when role = ? then 1 else 0 end) as gestionnaires', [Role::Gestionnaire->value])
            ->selectRaw('sum(case when role = ? then 1 else 0 end) as techniciens', [Role::Technicien->value])
            ->selectRaw('sum(case when is_active then 0 else 1 end) as suspended')
            ->first();

        return view('pages.users.index', compact('users', 'counts', 'filters'));
    }

    public function create(): View
    {
        return view('pages.users.form', ['user' => new User]);
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        $user = User::create($request->validated());

        return to_route('admin.users.index')->with('status', "Utilisateur {$user->name} créé.");
    }

    public function edit(User $user): View
    {
        return view('pages.users.form', compact('user'));
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $data = $request->validated();

        if (blank($data['password'] ?? null)) {
            unset($data['password']);
        }

        $user->update($data);

        return to_route('admin.users.index')->with('status', "Utilisateur {$user->name} mis à jour.");
    }

    /** Suspendre / réactiver un compte. */
    public function toggleStatus(Request $request, User $user): RedirectResponse
    {
        if ($user->is($request->user())) {
            return back()->with('error', 'Vous ne pouvez pas suspendre votre propre compte.');
        }

        $user->update(['is_active' => ! $user->is_active]);

        return back()->with('status', "Compte de {$user->name} ".($user->is_active ? 'réactivé.' : 'suspendu.'));
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        if ($user->is($request->user())) {
            return back()->with('error', 'Vous ne pouvez pas supprimer votre propre compte.');
        }

        $user->delete();

        return to_route('admin.users.index')->with('status', "Utilisateur {$user->name} supprimé.");
    }
}
