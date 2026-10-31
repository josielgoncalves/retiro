<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->statefulApi();

        // In production, PHP-FPM only ever receives requests from our own
        // Nginx (infra/nginx/prod.conf) over the private Docker network —
        // it's never reachable directly. That Nginx sits behind an
        // external reverse proxy that terminates HTTPS and forwards
        // X-Forwarded-Proto/Host/For, and it already rewrites the client
        // IP via ngx_http_realip_module before anything reaches Laravel.
        // Because of that IP rewrite, a specific trusted-proxy IP list
        // would never match here (REMOTE_ADDR is the real client by the
        // time PHP sees it) — trusting '*' is what actually works, and is
        // safe only because nothing but that Nginx can ever call PHP-FPM.
        $middleware->trustProxies(
            at: '*',
            headers: SymfonyRequest::HEADER_X_FORWARDED_FOR
                | SymfonyRequest::HEADER_X_FORWARDED_HOST
                | SymfonyRequest::HEADER_X_FORWARDED_PROTO,
        );

        $middleware->alias([
            'active' => \App\Http\Middleware\EnsureUserIsActive::class,
            'portal.token' => \App\Http\Middleware\AuthenticatePortalToken::class,
            'private.headers' => \App\Http\Middleware\SetPrivatePortalHeaders::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
