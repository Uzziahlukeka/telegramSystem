<?php

declare(strict_types=1);

use Monolog\Handler\NullHandler;
use Psr\Log\LoggerInterface;
use Uzhlaravel\Telegramlogs\ActivityLogger;
use Uzhlaravel\TelegramSystem\Telegram\TelegramlogsBridge;
use Uzhlaravel\TelegramSystem\TelegramSystem;

beforeEach(function () {
    // Route the telegramlogs channel to a no-op handler so logging never hits
    // the network in tests, while still exercising the real delegation path.
    config()->set('logging.channels.telegram', [
        'driver' => 'monolog',
        'handler' => NullHandler::class,
    ]);
});

it('delegates direct messaging to telegramlogs', function () {
    // telegramlogs gates non-production environments and returns false without
    // performing any outbound request — proof the call was delegated.
    expect(app(TelegramlogsBridge::class)->message('hello'))->toBeFalse()
        ->and(app(TelegramlogsBridge::class)->toChat('-1001', 'hello'))->toBeFalse();
});

it('exposes the telegramlogs activity logger', function () {
    expect(app(TelegramlogsBridge::class)->activity())->toBeInstanceOf(ActivityLogger::class);
});

it('logs through the telegramlogs channel', function () {
    $bridge = app(TelegramlogsBridge::class);

    expect($bridge->logger())->toBeInstanceOf(LoggerInterface::class);

    // Must not throw against the telegramlogs-backed channel.
    $bridge->log('error', 'something happened');
});

it('surfaces the telegramlogs capabilities through the TelegramSystem facade', function () {
    $system = app(TelegramSystem::class);

    expect($system->telegramlogs())->toBeInstanceOf(TelegramlogsBridge::class)
        ->and($system->message('hi'))->toBeFalse()
        ->and($system->activity())->toBeInstanceOf(ActivityLogger::class);

    $system->log('info', 'via facade');
});
