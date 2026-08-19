<?php

namespace App\Http\Controllers;

use App\Models\CostEntry;
use App\Support\AccessScope;
use Illuminate\Http\Request;

class CostController extends Controller
{
    public function index(Request $request)
    {
        $query = CostEntry::with('tire.model', 'user')->latest('occurred_at');
        AccessScope::applyCompany($query, $request->user());

        return view('costs.index', [
            'entries' => $query->paginate(40),
            'total' => (clone $query)->sum('amount'),
        ]);
    }
}
