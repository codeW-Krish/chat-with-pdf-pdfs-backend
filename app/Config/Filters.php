<?php

namespace Config;

use CodeIgniter\Config\Filters as BaseFilters;
use CodeIgniter\Filters\CSRF;
use CodeIgniter\Filters\DebugToolbar;
use CodeIgniter\Filters\ForceHTTPS;
use CodeIgniter\Filters\Honeypot;
use CodeIgniter\Filters\InvalidChars;
use CodeIgniter\Filters\PageCache;
use CodeIgniter\Filters\PerformanceMetrics;
use CodeIgniter\Filters\SecureHeaders;

class Filters extends BaseFilters
{
    public array $aliases = [
        'csrf'       => CSRF::class,
        'toolbar'    => DebugToolbar::class,
        'honeypot'   => Honeypot::class,
        'invalidchars'=> InvalidChars::class,
        'secureheaders'=> SecureHeaders::class,
        'cors'    => \App\Filters\CorsFilter::class, // <-- make sure this exists
        'forcehttps' => ForceHTTPS::class,
        'pagecache'  => PageCache::class,
        'performance'=> PerformanceMetrics::class,
        'jwtauth'    => \App\Filters\JWTAuth::class,
    ];

    public array $globals = [
        'before' => [
            'cors', // <-- apply CORS globally to all requests
        ],
        'after' => [
            'cors', // <-- apply CORS headers after response
            'toolbar',
        ],
    ];

    public array $methods = [];
    public array $filters = [
        'cors' => [
            'before' => ['auth/*', 'api/*', 'public/*'],
            'after' => ['auth/*', 'api/*', 'public/*']
        ],
        'jwtauth' => [
            'before' => ['api/*'],
            'after' => ['api/*']
        ]
    ];
    
}
