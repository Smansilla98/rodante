<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        return view('notifications.index', [
            'notifications' => $user->notifications()->limit(50)->get(),
        ]);
    }

    public function read(Request $request, string $id)
    {
        $n = $request->user()->notifications()->where('id', $id)->firstOrFail();
        $n->markAsRead();

        $url = $n->data['url'] ?? route('dashboard');

        return redirect($url);
    }
}
