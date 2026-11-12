<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Trusted Proxies
    |--------------------------------------------------------------------------
    |
    | Set trusted reverse proxy IPs or CIDR ranges (e.g. Nginx inside Podman
    | bridge network and loopback) so X-Forwarded-* headers from Cloudflare
    | Tunnel or TLS reverse proxies resolve client IP and HTTPS scheme safely.
    |
    */

    'proxies' => env('TRUSTED_PROXIES', '127.0.0.1,::1,10.0.0.0/8,172.16.0.0/12,192.168.0.0/16'),

];
