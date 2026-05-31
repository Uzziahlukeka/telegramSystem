<?php

declare(strict_types=1);

namespace Uzhlaravel\TelegramSystem\Actions;

use Illuminate\Contracts\Events\Dispatcher;
use Uzhlaravel\TelegramSystem\Contracts\TicketRepositoryInterface;
use Uzhlaravel\TelegramSystem\Events\TicketAssigned;
use Uzhlaravel\TelegramSystem\Tickets\Ticket;
use Uzhlaravel\TelegramSystem\Tickets\TicketPolicy;

/**
 * Implements the "first non-admin replier becomes the agent" rule.
 *
 * The actual flip is delegated to the repository's atomic, conditional UPDATE
 * so that two simultaneous first replies cannot both win. Only the winner
 * dispatches {@see TicketAssigned}.
 */
final class AssignTicketAction
{
    public function __construct(
        private readonly TicketRepositoryInterface $tickets,
        private readonly TicketPolicy $policy,
        private readonly Dispatcher $events,
    ) {}

    /**
     * Returns the (possibly already-assigned) ticket. Non-eligible callers are
     * a no-op rather than an error, because inbound replies routinely come from
     * the owner or admins who do not become agents.
     */
    public function execute(Ticket $ticket, int $agentId, ?string $agentUsername = null): Ticket
    {
        // Action re-check (authorization layer 3): only eligible repliers claim.
        if (! $this->policy->assign($agentId, $ticket)) {
            return $ticket;
        }

        $won = $this->tickets->assignAgent($ticket, $agentId, $agentUsername);

        $fresh = $this->tickets->find($ticket->id) ?? $ticket;

        if ($won) {
            $this->events->dispatch(new TicketAssigned($fresh, $agentId));
        }

        return $fresh;
    }
}
