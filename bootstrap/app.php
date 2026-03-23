<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(at: '*');

        // Livewire's file-upload endpoint uses its own token system.
        // Excluding it from CSRF prevents 419 errors on shared hosting where
        // the upload XHR can arrive in a separate PHP process from the page load.
        $middleware->validateCsrfTokens(except: [
            'livewire/upload-file',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
