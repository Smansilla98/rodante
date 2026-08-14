<?php

namespace App\Support\Qa;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Foundation\Testing\Concerns\InteractsWithAuthentication;
use Illuminate\Foundation\Testing\Concerns\MakesHttpRequests;

class QaHttp
{
    use InteractsWithAuthentication;
    use MakesHttpRequests;

    public function __construct(protected Application $app) {}
}
