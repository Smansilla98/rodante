<?php

namespace Tests\Feature;

use Tests\TestCase;

class AccessibilitySurfaceTest extends TestCase
{
    public function test_authentication_forms_expose_labels(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertSee('for="username"', false)
            ->assertSee('for="password"', false);

        $this->get('/olvide-contrasena')
            ->assertOk()
            ->assertSee('for="login"', false);
    }
}
