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

    /*
    |--------------------------------------------------------------------------
    | Informe semanal por correo (programado)
    |--------------------------------------------------------------------------
    |
    | Lunes 08:00 (timezone de la app). Requiere SMTP real (MAIL_MAILER=smtp).
    | Si WEEKLY_REPORT_EMAILS está vacío, se envía a jefes/admins activos
    | de cada empresa. Si tiene valores, esos correos reciben el de cada empresa.
    |
    */
    'weekly_report' => [
        'enabled' => (bool) env('RODANTE_WEEKLY_REPORT_ENABLED', false),
        'emails' => array_values(array_filter(array_map(
            static fn (string $email): string => trim($email),
            explode(',', (string) env('RODANTE_WEEKLY_REPORT_EMAILS', ''))
        ))),
    ],

];
