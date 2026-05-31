<?php

declare(strict_types=1);

use Uzhlaravel\TelegramSystem\Actions\AssignTicketAction;
use Uzhlaravel\TelegramSystem\Contracts\TicketRepositoryInterface;
use Uzhlaravel\TelegramSystem\Events\TicketAssigned;
use Uzhlaravel\TelegramSystem\Tickets\Ticket;
use Uzhlaravel\TelegramSystem\Tickets\TicketStatus;

it('assigns the first non-admin replier as the agent', function () {
    $ticket = Ticket::factory()->create(['owner_id' => 111, 'agent_id' => null]);

    $action = app(AssignTicketAction::class);
    $result = $action->execute($ticket, 222, 'first');

    expect($result->agent_id)->toBe(222)
        ->and($result->status)->toBe(TicketStatus::Assigned);
});

it('guards against a race so only the first reply wins', function () {
    $ticket = Ticket::factory()->create(['owner_id' => 111, 'agent_id' => null]);
    $repo = app(TicketRepositoryInterface::class);

    // Two "simultaneous" first replies against the same unassigned ticket.
    $firstWon = $repo->assignAgent($ticket, 222, 'first');
    $secondWon = $repo->assignAgent($ticket, 333, 'second');

    expect($firstWon)->toBeTrue()
        ->and($secondWon)->toBeFalse()
        ->and($ticket->fresh()->agent_id)->toBe(222);
});

it('only dispatches TicketAssigned for the winning reply', function () {
    Event::fake([TicketAssigned::class]);

    $ticket = Ticket::factory()->create(['owner_id' => 111, 'agent_id' => null]);
    $action = app(AssignTicketAction::class);

    $action->execute($ticket, 222, 'first');
    $action->execute($ticket->fresh(), 333, 'second');

    Event::assertDispatchedTimes(TicketAssigned::class, 1);
});

it('does not reassign an already assigned ticket', function () {
    $ticket = Ticket::factory()->assigned(222, 'first')->create(['owner_id' => 111]);

    $result = app(AssignTicketAction::class)->execute($ticket, 333, 'second');

    expect($result->agent_id)->toBe(222);
});
