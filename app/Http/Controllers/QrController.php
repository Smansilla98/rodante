<?php

namespace App\Http\Controllers;

use App\Models\Tire;
use App\Support\AccessScope;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Http\Request;

class QrController extends Controller
{
    public function show(Request $request, string $token)
    {
        if (! $request->user()) {
            return redirect()->guest(route('login'));
        }
        $tire = Tire::query()->where('public_token', $token)->firstOrFail();
        AccessScope::abortUnlessTire($request->user(), $tire->id);

        return redirect()->route('tires.show', $tire);
    }

    public function image(Request $request, Tire $tire)
    {
        AccessScope::abortUnlessTire($request->user(), $tire->id);
        if (! $tire->public_token) {
            abort(404);
        }
        $url = route('qr.resolve', $tire->public_token);
        $renderer = new ImageRenderer(new RendererStyle(280), new SvgImageBackEnd);
        $svg = (new Writer($renderer))->writeString($url);

        return response($svg, 200, [
            'Content-Type' => 'image/svg+xml',
            'Cache-Control' => 'private, max-age=86400',
        ]);
    }
}
