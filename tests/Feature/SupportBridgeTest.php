<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Uzhlaravel\TelegramSystem\DTOs\TelegramMessageData;
use Uzhlaravel\TelegramSystem\Telegram\SupportBridge;
use Uzhlaravel\TelegramSystem\Tickets\Ticket;
use Uzhlaravel\TelegramSystem\Tickets\TicketMessage;
use Uzhlaravel\TelegramSystem\Tickets\TicketStatus;

use function Pest\Laravel\withHeaders;

beforeEach(function () {
    // Each send/copy returns a fresh message id so header / user / agent rows differ.
    $next = 0;
    Http::fake(function () use (&$next) {
        $next++;

        return Http::response(['ok' => true, 'result' => ['message_id' => 700 + $next]]);
    });

    config()->set('telegramsystem.support_bridge.enabled', true);
    config()->set('telegramsystem.support_bridge.bot', 'support');

    $this->bridge = app(SupportBridge::class);
});

/**
 * Build a Telegram "message" object for a contact direct-messaging the bot.
 *
 * @return array<string, mixed>
 */
function privateMessage(int $contactId, string $text, int $messageId = 10): array
{
    return [
        'message_id' => $messageId,
        'from' => [
            'id' => $contactId,
            'is_bot' => false,
            'username' => 'contact'.$contactId,
            'first_name' => 'Ada',
            'last_name' => 'Lovelace',
        ],
        'chat' => ['id' => $contactId, 'type' => 'private'],
        'text' => $text,
    ];
}

it('opens a Telegram ticket from a private message and copies it into the group', function () {
    $ticket = $this->bridge->handleContactMessage(
        'support',
        TelegramMessageData::fromResponse(privateMessage(777, 'I need help')),
    );

    expect($ticket)->not->toBeNull()
        ->and($ticket->source)->toBe(Ticket::SOURCE_TELEGRAM)
        ->and($ticket->owner_id)->toBe(777)
        ->and($ticket->chat_id)->toBe('-2002')
        ->and($ticket->ticket_number)->not->toBeNull()
        ->and($ticket->fresh()->status)->toBe(TicketStatus::Pending);

    // A header anchor plus the copied contact message are both recorded.
    expect($ticket->messages()->where('direction', TicketMessage::DIRECTION_HEADER)->count())->toBe(1)
        ->and($ticket->messages()->where('direction', TicketMessage::DIRECTION_FROM_USER)->count())->toBe(1);

    // The header is posted to the group and the message is copied into it.
    Http::assertSent(fn ($r) => str_contains($r->url(), 'sendMessage') && $r['chat_id'] === '-2002');
    Http::assertSent(fn ($r) => str_contains($r->url(), 'copyMessage')
        && $r['chat_id'] === '-2002'
        && $r['from_chat_id'] === '777');

    // The contact is acknowledged in their private chat.
    Http::assertSent(fn ($r) => str_contains($r->url(), 'sendMessage')
        && $r['chat_id'] === '777'
        && str_contains((string) $r['text'], $ticket->ticket_number));
});

it('greets on /start without opening a ticket', function () {
    $ticket = $this->bridge->handleContactMessage(
        'support',
        TelegramMessageData::fromResponse(privateMessage(888, '/start')),
    );

    expect($ticket)->toBeNull()
        ->and(Ticket::query()->count())->toBe(0);

    Http::assertSent(fn ($r) => str_contains($r->url(), 'sendMessage') && $r['chat_id'] === '888');
    Http::assertNotSent(fn ($r) => str_contains($r->url(), 'copyMessage'));
});

it('reuses the same ticket and header for a returning contact', function () {
    $first = $this->bridge->handleContactMessage('support', TelegramMessageData::fromResponse(privateMessage(777, 'First', 10)));
    $second = $this->bridge->handleContactMessage('support', TelegramMessageData::fromResponse(privateMessage(777, 'Second', 11)));

    expect($second->id)->toBe($first->id);

    // One header, two copied contact messages.
    expect($first->messages()->where('direction', TicketMessage::DIRECTION_HEADER)->count())->toBe(1)
        ->and($first->messages()->where('direction', TicketMessage::DIRECTION_FROM_USER)->count())->toBe(2);
});

it('copies an agent reply back to the contact and assigns the agent', function () {
    $ticket = $this->bridge->handleContactMessage('support', TelegramMessageData::fromResponse(privateMessage(777, 'Help me')));
    $userMessageId = $ticket->messages()->where('direction', TicketMessage::DIRECTION_FROM_USER)->value('group_message_id');

    $update = [
        'update_id' => 5,
        'message' => [
            'message_id' => 9001,
            'from' => ['id' => 4242, 'is_bot' => false, 'username' => 'agent'],
            'chat' => ['id' => -2002, 'type' => 'supergroup'],
            'text' => 'Have you tried turning it off and on?',
            'reply_to_message' => ['message_id' => $userMessageId],
        ],
    ];

    withHeaders(['X-Telegram-Bot-Api-Secret-Token' => 'support-secret'])
        ->postJson('/telegram/webhook/support', $update)
        ->assertOk();

    $fresh = $ticket->fresh();

    expect($fresh->agent_id)->toBe(4242)
        ->and($fresh->agent_username)->toBe('agent')
        ->and($fresh->status)->toBe(TicketStatus::Assigned);

    // The agent's reply is copied into the contact's private chat (777).
    Http::assertSent(fn ($r) => str_contains($r->url(), 'copyMessage')
        && $r['chat_id'] === '777'
        && $r['from_chat_id'] === '-2002'
        && (int) $r['message_id'] === 9001);
});

it('closes a ticket from a group /close reply and notifies the contact', function () {
    $ticket = $this->bridge->handleContactMessage('support', TelegramMessageData::fromResponse(privateMessage(777, 'Help me')));
    $headerId = $ticket->headerMessage()?->group_message_id;

    $update = [
        'update_id' => 6,
        'message' => [
            'message_id' => 9100,
            'from' => ['id' => 4242, 'is_bot' => false, 'username' => 'agent'],
            'chat' => ['id' => -2002, 'type' => 'supergroup'],
            'text' => '/close',
            'reply_to_message' => ['message_id' => $headerId],
        ],
    ];

    withHeaders(['X-Telegram-Bot-Api-Secret-Token' => 'support-secret'])
        ->postJson('/telegram/webhook/support', $update)
        ->assertOk();

    expect($ticket->fresh()->status)->toBe(TicketStatus::Closed);

    // The contact is told their ticket was closed, in their private chat.
    Http::assertSent(fn ($r) => str_contains($r->url(), 'sendMessage')
        && $r['chat_id'] === '777'
        && str_contains((string) $r['text'], $ticket->ticket_number));
});

it('ignores an agent reply that does not map to any ticket', function () {
    $handled = $this->bridge->handleAgentReply('support', TelegramMessageData::fromResponse([
        'message_id' => 1,
        'from' => ['id' => 4242, 'is_bot' => false],
        'chat' => ['id' => -2002, 'type' => 'supergroup'],
        'text' => 'random chatter',
        'reply_to_message' => ['message_id' => 999999],
    ]));

    expect($handled)->toBeFalse();
});
