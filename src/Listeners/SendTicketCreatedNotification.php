<?php

declare(strict_types=1);

namespace Uzhlaravel\TelegramSystem\Listeners;

use Uzhlaravel\TelegramSystem\Events\TicketCreated;
use Uzhlaravel\TelegramSystem\Telegram\MultiBotManager;

final class SendTicketCreatedNotification
{
    public function __construct(private readonly MultiBotManager $bots) {}

    public function handle(TicketCreated $event): void
    {
        $ticket = $event->ticket;
        $owner = $ticket->owner_username !== null ? '@'.$ticket->owner_username : (string) $ticket->owner_id;

        $text = sprintf(
            "%s New ticket #%d opened\nContact: %s\nStatus: %s",
            $ticket->status->emoji(),
            $ticket->id,
            $owner,
            $ticket->status->label(),
        );

        $options = $ticket->message_thread_id !== null
            ? ['message_thread_id' => $ticket->message_thread_id]
            : [];

        $this->bots->sendMessage($ticket->bot, $ticket->chat_id, $text, $options);
    }
}
