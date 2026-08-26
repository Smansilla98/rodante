<?php

namespace App\Http\Controllers;

use App\Models\Tire;
use App\Models\TirePhoto;
use App\Services\TirePhotoService;
use App\Support\AccessScope;
use Illuminate\Http\Request;

class TirePhotoController extends Controller
{
    public function show(Request $request, Tire $tire, TirePhoto $photo, TirePhotoService $photos)
    {
        AccessScope::abortUnlessTire($request->user(), $tire->id);
        abort_unless((int) $photo->tire_id === (int) $tire->id, 404);

        return response($photos->contents($photo), 200, [
            'Content-Type' => $photo->mime ?: 'image/jpeg',
            'Content-Disposition' => 'inline; filename="'.str_replace('"', '', (string) $photo->original_name).'"',
            'Cache-Control' => 'private, max-age=300',
        ]);
    }
}
