<?php

return [
    'local_ca_path' => env(
        'GSS_LOCAL_CA_PATH',
        storage_path('app/gss-certificates/gss-local-ca.crt'),
    ),
    'http_port' => (int) env('HTTP_FORWARD_PORT', 80),
];
