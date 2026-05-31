<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Uzhlaravel\TelegramSystem\Tickets\Ticket;

use function Pest\Laravel\withHeaders;

function update(int $chatId, int $fromId, string $text = 'I need help', ?int $threadId = null): array
{
    $message = [
        'message_id' => 10,
        'from' => ['id' => $fromId, 'is_bot' => false, 'username' => 'user'.$fromId],
        'chat' => ['id' => $chatId, 'type' => 'supergroup'],
        'text' => $text,
    ];

    if ($threadId !== null) {
        $message['message_thread_id'] = $threadId;
        $message['is_topic_message'] = true;
    }

    return ['update_id' => 1, 'message' => $message];
}

beforeEach(function () {
    Http::fake(['*' => Http::response(['ok' => true, 'result' => []])]);
});

it('routes an inbound update to the correct bot and opens a ticket', function () {
    withHeaders(['X-Telegram-Bot-Api-Secret-Token' => 'support-secret'])
        ->postJson('/telegram/webhook/support', update(-2002, 12345))
        ->assertOk();

    $ticket = Ticket::query()->where('bot', 'support')->first();

    expect($ticket)->not->toBeNull()
        ->and($ticket->owner_id)->toBe(12345)
        ->and($ticket->chat_id)->toBe('-2002');
});

it('rejects an invalid secret token', function () {
    withHeaders(['X-Telegram-Bot-Api-Secret-Token' => 'wrong'])
        ->postJson('/telegram/webhook/support', update(-2002, 12345))
        ->assertForbidden();

    expect(Ticket::query()->count())->toBe(0);
});

it('returns 404 for an unknown bot', function () {
    withHeaders(['X-Telegram-Bot-Api-Secret-Token' => 'whatever'])
        ->postJson('/telegram/webhook/ghostbot', update(-2002, 12345))
        ->assertNotFound();
});

it('rejects an unauthorized reply into an existing ticket topic', function () {
    config()->set('telegramsystem.enforcement.delete_unauthorized', false);

    $ticket = Ticket::factory()->assigned(222, 'agent')->create([
        'bot' => 'support',
        'chat_id' => '-2002',
        'message_thread_id' => 55,
        'owner_id' => 111,
    ]);

    withHeaders(['X-Telegram-Bot-Api-Secret-Token' => 'support-secret'])
        ->postJson('/telegram/webhook/support', update(-2002, 999, 'butting in', 55))
        ->assertOk();

    expect($ticket->fresh()->agent_id)->toBe(222);
});
