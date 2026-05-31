<?php

declare(strict_types=1);

namespace Uzhlaravel\TelegramSystem\Listeners;

use Uzhlaravel\TelegramSystem\Events\TicketReopened;
use Uzhlaravel\TelegramSystem\Telegram\MultiBotManager;

final class SendTicketReopenedNotification
{
    public function __construct(private readonly MultiBotManager $bots) {}

    public function handle(TicketReopened $event): void
    {
        $ticket = $event->ticket;

        $text = sprintf(
            '%s Ticket #%d reopened by %d',
            $ticket->status->emoji(),
            $ticket->id,
            $event->reopenedBy,
        );

        $options = $ticket->message_thread_id !== null
            ? ['message_thread_id' => $ticket->message_thread_id]
            : [];

        $this->bots->sendMessage($ticket->bot, $ticket->chat_id, $text, $options);
    }
}
