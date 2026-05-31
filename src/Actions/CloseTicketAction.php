<?php

declare(strict_types=1);

namespace Uzhlaravel\TelegramSystem\Actions;

use Illuminate\Contracts\Events\Dispatcher;
use Uzhlaravel\TelegramSystem\Contracts\TicketRepositoryInterface;
use Uzhlaravel\TelegramSystem\Events\TicketClosed;
use Uzhlaravel\TelegramSystem\Events\TicketReopened;
use Uzhlaravel\TelegramSystem\Exceptions\UnauthorizedTicketAccessException;
use Uzhlaravel\TelegramSystem\Telegram\TopicManager;
use Uzhlaravel\TelegramSystem\Tickets\Ticket;
use Uzhlaravel\TelegramSystem\Tickets\TicketPolicy;
use Uzhlaravel\TelegramSystem\Tickets\TicketStatus;

/**
 * Closes (and, via {@see reopen()}, reopens) a ticket, keeping the forum topic
 * in sync. Both operations re-check authorization (layer 3) and throw
 * {@see UnauthorizedTicketAccessException} on denial.
 */
final class CloseTicketAction
{
    public function __construct(
        private readonly TicketRepositoryInterface $tickets,
        private readonly TopicManager $topics,
        private readonly TicketPolicy $policy,
        private readonly Dispatcher $events,
    ) {}

    public function execute(Ticket $ticket, int $actorId): Ticket
    {
        if (! $this->policy->close($actorId, $ticket)) {
            throw UnauthorizedTicketAccessException::for($actorId, $ticket, 'close');
        }

        $this->tickets->updateStatus($ticket, TicketStatus::Closed);
        $this->topics->syncStatus($ticket);
        $this->events->dispatch(new TicketClosed($ticket, $actorId));

        return $ticket;
    }

    public function reopen(Ticket $ticket, int $actorId): Ticket
    {
        if (! $this->policy->reopen($actorId, $ticket)) {
            throw UnauthorizedTicketAccessException::for($actorId, $ticket, 'reopen');
        }

        $this->tickets->updateStatus($ticket, TicketStatus::Reopened);
        $this->topics->syncStatus($ticket);
        $this->events->dispatch(new TicketReopened($ticket, $actorId));

        return $ticket;
    }
}
