<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Uzhlaravel\TelegramSystem\Telegram\MultiBotManager;

it('resolves a distinct, cached client per named bot', function () {
    $manager = app(MultiBotManager::class);

    $default = $manager->client('default');
    $support = $manager->client('support');

    expect($default)->not->toBe($support)
        ->and($manager->client('support'))->toBe($support)
        ->and($manager->has('support'))->toBeTrue()
        ->and($manager->webhookSecret('support'))->toBe('support-secret');
});

it('routes each bot to the correct token on the wire', function () {
    Http::fake(['*' => Http::response(['ok' => true, 'result' => []])]);
    $manager = app(MultiBotManager::class);

    $manager->client('support')->getMe();
    $manager->client('default')->getMe();

    Http::assertSent(fn ($request) => str_contains($request->url(), 'botSUPPORT-TOKEN'));
    Http::assertSent(fn ($request) => str_contains($request->url(), 'botDEFAULT-TOKEN'));
});

it('delegates the default bot to telegramlogs for simple sends', function () {
    Http::fake();
    config()->set('telegramsystem.use_telegramlogs_for_default', true);

    // telegramlogs gates non-production environments and returns false WITHOUT
    // ever touching this package's HTTP client — a unique marker of delegation.
    $result = app(MultiBotManager::class)->sendMessage('default', '-1001', 'hello');

    expect($result)->toBeFalse();
    Http::assertNothingSent();
});

it('uses this package\'s client (not telegramlogs) when delegation is disabled', function () {
    Http::fake(['*' => Http::response(['ok' => true, 'result' => ['message_id' => 1]])]);
    config()->set('telegramsystem.use_telegramlogs_for_default', false);

    app(MultiBotManager::class)->sendMessage('default', '-1001', 'hello');

    Http::assertSent(fn ($request) => str_contains($request->url(), 'botDEFAULT-TOKEN')
        && str_contains($request->url(), 'sendMessage'));
});

it('uses this package\'s client for named bots instead of telegramlogs', function () {
    Http::fake(['*' => Http::response(['ok' => true, 'result' => ['message_id' => 1]])]);

    app(MultiBotManager::class)->sendMessage('support', '-2002', 'hello');

    Http::assertSent(fn ($request) => str_contains($request->url(), 'botSUPPORT-TOKEN')
        && str_contains($request->url(), 'sendMessage'));
});
