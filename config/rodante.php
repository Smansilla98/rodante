<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Restablecimiento de contraseña por correo
    |--------------------------------------------------------------------------
    |
    | En producción el mailer suele ser "log": el enlace del login engaña.
    | Con false, las rutas de olvido/restablecimiento responden 404.
    | Activá cuando haya SMTP real (MAIL_MAILER=smtp, etc.).
    |
    */
    'password_reset_enabled' => (bool) env('RODANTE_PASSWORD_RESET_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | Content-Security-Policy
    |--------------------------------------------------------------------------
    |
    | true = Content-Security-Policy (enforce)
    | false = Content-Security-Policy-Report-Only (solo telemetría)
    |
    */
    'csp_enforce' => (bool) env('RODANTE_CSP_ENFORCE', true),

];
