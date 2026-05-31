<?php

declare(strict_types=1);

namespace Uzhlaravel\TelegramSystem\Listeners;

use Uzhlaravel\TelegramSystem\Events\TicketAssigned;
use Uzhlaravel\TelegramSystem\Telegram\MultiBotManager;

final class SendTicketAssignedNotification
{
    public function __construct(private readonly MultiBotManager $bots) {}

    public function handle(TicketAssigned $event): void
    {
        $ticket = $event->ticket;
        $agent = $ticket->agent_username !== null ? '@'.$ticket->agent_username : (string) $event->agentId;

        $text = sprintf(
            '%s Ticket #%d assigned to %s',
            $ticket->status->emoji(),
            $ticket->id,
            $agent,
        );

        $options = $ticket->message_thread_id !== null
            ? ['message_thread_id' => $ticket->message_thread_id]
            : [];

        $this->bots->sendMessage($ticket->bot, $ticket->chat_id, $text, $options);
    }
}
