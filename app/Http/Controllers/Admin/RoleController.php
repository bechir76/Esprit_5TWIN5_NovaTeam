<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\View\View;

class RoleController extends Controller
{
    /** Les 4 rôles avec le nombre d'utilisateurs de chacun (une seule requête groupée). */
    public function index(): View
    {
        $counts = User::query()
            ->selectRaw('role, count(*) as total')
            ->groupBy('role')
            ->pluck('total', 'role');

        $roles = collect(Role::cases())->map(fn (Role $role) => [
            'role' => $role,
            'count' => (int) ($counts[$role->value] ?? 0),
        ]);

        return view('back.roles.index', compact('roles'));
    }
}
