<?php

namespace App\Http\Controllers;

use App\Services\IntegrityService;
use Illuminate\Http\Request;

class IntegrityController extends Controller
{
    public function index(Request $request, IntegrityService $integrity)
    {
        abort_unless($request->user()->role->canRetireOrRecap(), 403);

        return view('integrity.index', [
            'findings' => $integrity->findings($request->user()),
        ]);
    }
}
