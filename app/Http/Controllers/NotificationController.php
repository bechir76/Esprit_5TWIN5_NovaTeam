<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Centre de notifications du citoyen (dons confirmés, avancement des projets soutenus). */
class NotificationController extends Controller
{
    public function index(Request $request): View
    {
        return view('pages.projects.notifications', [
            'notifications' => $request->user()->notifications()->paginate(15),
        ]);
    }

    public function markAllRead(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications->markAsRead();

        return back()->with('status', 'Notifications marquées comme lues.');
    }
}
