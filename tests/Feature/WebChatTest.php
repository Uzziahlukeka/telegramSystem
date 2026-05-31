<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Uzhlaravel\TelegramSystem\Tickets\Ticket;
use Uzhlaravel\TelegramSystem\Tickets\TicketMessage;
use Uzhlaravel\TelegramSystem\Tickets\TicketStatus;
use Uzhlaravel\TelegramSystem\WebChat\WebChatService;

use function Pest\Laravel\withHeaders;

beforeEach(function () {
    // Each send returns a new Telegram message id so header / user rows differ.
    $next = 0;
    Http::fake(function () use (&$next) {
        $next++;

        return Http::response(['ok' => true, 'result' => ['message_id' => 500 + $next]]);
    });

    config()->set('telegramsystem.web_chat.bot', 'support');

    $this->web = app(WebChatService::class);
});

it('opens a web ticket and records the header and first message', function () {
    $ticket = $this->web->send('session-token-1', 'Ada', 'ada@example.com', 'Hello there');

    expect($ticket->source)->toBe(Ticket::SOURCE_WEB)
        ->and($ticket->owner_id)->toBeNull()
        ->and($ticket->web_session_token)->toBe('session-token-1')
        ->and($ticket->web_email)->toBe('ada@example.com')
        ->and($ticket->ticket_number)->not->toBeNull()
        ->and($ticket->fresh()->status)->toBe(TicketStatus::Pending);

    expect($ticket->messages()->where('direction', TicketMessage::DIRECTION_HEADER)->count())->toBe(1)
        ->and($ticket->messages()->where('direction', TicketMessage::DIRECTION_FROM_USER)->count())->toBe(1);
});

it('reuses the same ticket for a returning session', function () {
    $first = $this->web->send('session-token-2', 'Ada', null, 'First');
    $second = $this->web->send('session-token-2', 'Ada', null, 'Second');

    expect($second->id)->toBe($first->id);

    // One header, two user messages.
    expect($first->messages()->where('direction', TicketMessage::DIRECTION_HEADER)->count())->toBe(1)
        ->and($first->messages()->where('direction', TicketMessage::DIRECTION_FROM_USER)->count())->toBe(2);
});

it('captures an agent reply to a web ticket through the webhook', function () {
    $ticket = $this->web->send('session-token-3', 'Ada', null, 'I need help');

    $headerId = $ticket->headerMessage()?->group_message_id;
    expect($headerId)->not->toBeNull();

    $update = [
        'update_id' => 7,
        'message' => [
            'message_id' => 9001,
            'from' => ['id' => 4242, 'is_bot' => false, 'username' => 'agent'],
            'chat' => ['id' => -2002, 'type' => 'supergroup'],
            'text' => 'Happy to help!',
            'reply_to_message' => ['message_id' => $headerId],
        ],
    ];

    withHeaders(['X-Telegram-Bot-Api-Secret-Token' => 'support-secret'])
        ->postJson('/telegram/webhook/support', $update)
        ->assertOk();

    $agentMessages = $ticket->messages()->where('direction', TicketMessage::DIRECTION_FROM_AGENT)->get();

    expect($agentMessages)->toHaveCount(1)
        ->and($agentMessages->first()->content)->toBe('Happy to help!')
        ->and($ticket->fresh()->status)->toBe(TicketStatus::Assigned);

    // The conversation replays both sides in order.
    $conversation = $this->web->conversation($ticket->fresh());
    expect($conversation)->toHaveCount(2)
        ->and($conversation[0]['direction'])->toBe(TicketMessage::DIRECTION_FROM_USER)
        ->and($conversation[1]['direction'])->toBe(TicketMessage::DIRECTION_FROM_AGENT);
});

it('does not record the same agent reply twice', function () {
    $ticket = $this->web->send('session-token-4', 'Ada', null, 'Help');
    $headerId = $ticket->headerMessage()?->group_message_id;

    $update = [
        'update_id' => 8,
        'message' => [
            'message_id' => 9100,
            'from' => ['id' => 4242, 'is_bot' => false, 'username' => 'agent'],
            'chat' => ['id' => -2002, 'type' => 'supergroup'],
            'text' => 'On it',
            'reply_to_message' => ['message_id' => $headerId],
        ],
    ];

    foreach ([1, 2] as $_) {
        withHeaders(['X-Telegram-Bot-Api-Secret-Token' => 'support-secret'])
            ->postJson('/telegram/webhook/support', $update)
            ->assertOk();
    }

    expect($ticket->messages()->where('direction', TicketMessage::DIRECTION_FROM_AGENT)->count())->toBe(1);
});
