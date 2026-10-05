<?php

namespace App\Http\Controllers;

use App\Models\Infrastructure;
use Illuminate\View\View;

/** Accueil du site citoyen. */
class HomeController extends Controller
{
    public function __invoke(): View
    {
        return view('front.home', [
            'infrastructureCount' => Infrastructure::count(),
        ]);
    }
}
