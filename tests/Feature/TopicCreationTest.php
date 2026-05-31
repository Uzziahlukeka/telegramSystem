<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Uzhlaravel\TelegramSystem\Actions\CreateTicketAction;

beforeEach(function () {
    config()->set('telegramsystem.topics.enabled', true);
});

it('creates a forum topic and stores the thread id', function () {
    Http::fake([
        '*createForumTopic*' => Http::response([
            'ok' => true,
            'result' => ['message_thread_id' => 777, 'name' => 'Ticket'],
        ]),
        '*' => Http::response(['ok' => true, 'result' => []]),
    ]);

    $ticket = app(CreateTicketAction::class)->execute('default', '-1001', 111, 'owner');

    expect($ticket->message_thread_id)->toBe(777);

    Http::assertSent(fn ($request) => str_contains($request->url(), 'createForumTopic'));
});

it('falls back to the main chat when the group is not a forum', function () {
    Http::fake([
        '*createForumTopic*' => Http::response([
            'ok' => false,
            'error_code' => 400,
            'description' => 'Bad Request: the group is not a forum',
        ]),
        '*' => Http::response(['ok' => true, 'result' => []]),
    ]);

    $ticket = app(CreateTicketAction::class)->execute('default', '-1001', 111, 'owner');

    expect($ticket->message_thread_id)->toBeNull();
});

it('does not attempt topic creation when topics are disabled', function () {
    config()->set('telegramsystem.topics.enabled', false);
    Http::fake();

    $ticket = app(CreateTicketAction::class)->execute('default', '-1001', 111, 'owner');

    expect($ticket->message_thread_id)->toBeNull();
    Http::assertNotSent(fn ($request) => str_contains($request->url(), 'createForumTopic'));
});
