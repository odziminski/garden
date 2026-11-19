<?php

namespace App\Http\Middleware;

use Illuminate\Http\Middleware\TrustHosts as Middleware;

class TrustHosts extends Middleware
{
    public function hosts(): array
    {
        $host = parse_url(config('app.url'), PHP_URL_HOST);

        return $host ? ['^'.preg_quote($host, '/').'$'] : [];
    }
}
