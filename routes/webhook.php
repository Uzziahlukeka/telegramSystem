<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Uzhlaravel\TelegramSystem\Http\Controllers\WebhookController;

if (! (bool) config('telegramsystem.webhook.enabled', true)) {
    return;
}

/** @var array<int, string> $middleware */
$middleware = (array) config('telegramsystem.webhook.middleware', ['api']);

/** @var string $path */
$path = (string) config('telegramsystem.webhook.path', 'telegram/webhook');

Route::middleware($middleware)->group(function () use ($path): void {
    Route::post($path.'/{bot}', WebhookController::class)
        ->name('telegramsystem.webhook');
});
