<?php

declare(strict_types=1);

namespace Uzhlaravel\TelegramSystem\Listeners;

use Uzhlaravel\TelegramSystem\Events\TicketClosed;
use Uzhlaravel\TelegramSystem\Telegram\MultiBotManager;

final class SendTicketClosedNotification
{
    public function __construct(private readonly MultiBotManager $bots) {}

    public function handle(TicketClosed $event): void
    {
        $ticket = $event->ticket;

        $text = sprintf(
            '%s Ticket #%d closed by %d',
            $ticket->status->emoji(),
            $ticket->id,
            $event->closedBy,
        );

        $options = $ticket->message_thread_id !== null
            ? ['message_thread_id' => $ticket->message_thread_id]
            : [];

        $this->bots->sendMessage($ticket->bot, $ticket->chat_id, $text, $options);
    }
}
