<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Allow private / internal targets
    |--------------------------------------------------------------------------
    |
    | Scanning private, loopback or reserved IP ranges is blocked by default so
    | the scanner cannot be abused to reach internal services (SSRF). Enable
    | it only on a local machine when you want to scan your own local APIs.
    |
    */

    'allow_private' => (bool) env('SCANNER_ALLOW_PRIVATE', false),

    /*
    | Seconds to wait for the target to respond.
    */

    'timeout' => (int) env('SCANNER_TIMEOUT', 10),

    /*
    | Path to a CA certificate bundle used to verify HTTPS targets. Leave empty
    | to use PHP's default (curl.cainfo). Needed on Windows setups where PHP
    | ships without one, e.g. Laragon: C:/laragon/etc/ssl/cacert.pem
    */

    'ca_bundle' => env('SCANNER_CA_BUNDLE'),

    /*
    | Responses slower than this (milliseconds) are reported as a finding.
    */

    'slow_threshold_ms' => 2000,

];
