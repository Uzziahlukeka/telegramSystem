<?php

declare(strict_types=1);

use Uzhlaravel\TelegramSystem\Tickets\Ticket;
use Uzhlaravel\TelegramSystem\Tickets\TicketPolicy;

const OWNER = 111;
const AGENT = 222;
const STRANGER = 333;
const ADMIN = 999;

function makePolicy(): TicketPolicy
{
    return new TicketPolicy([ADMIN]);
}

it('lets the owner view their ticket but rejects a stranger', function () {
    $ticket = Ticket::factory()->make(['owner_id' => OWNER, 'agent_id' => AGENT]);

    expect(makePolicy()->view(OWNER, $ticket))->toBeTrue()
        ->and(makePolicy()->view(AGENT, $ticket))->toBeTrue()
        ->and(makePolicy()->view(STRANGER, $ticket))->toBeFalse();
});

it('grants admins override access to every ticket', function () {
    $ticket = Ticket::factory()->make(['owner_id' => OWNER, 'agent_id' => AGENT]);

    expect(makePolicy()->view(ADMIN, $ticket))->toBeTrue()
        ->and(makePolicy()->close(ADMIN, $ticket))->toBeTrue()
        ->and(makePolicy()->reopen(ADMIN, $ticket))->toBeTrue();
});

it('treats an unassigned active ticket as claimable by a non-admin replier', function () {
    $ticket = Ticket::factory()->make(['owner_id' => OWNER, 'agent_id' => null]);

    expect(makePolicy()->assign(STRANGER, $ticket))->toBeTrue()
        ->and(makePolicy()->reply(STRANGER, $ticket))->toBeTrue();
});

it('never lets an admin or the owner become the assigned agent', function () {
    $ticket = Ticket::factory()->make(['owner_id' => OWNER, 'agent_id' => null]);

    expect(makePolicy()->assign(ADMIN, $ticket))->toBeFalse()
        ->and(makePolicy()->assign(OWNER, $ticket))->toBeFalse();
});

it('locks replies to participants once a ticket is assigned', function () {
    $ticket = Ticket::factory()->make(['owner_id' => OWNER, 'agent_id' => AGENT]);

    expect(makePolicy()->reply(STRANGER, $ticket))->toBeFalse()
        ->and(makePolicy()->reply(AGENT, $ticket))->toBeTrue()
        ->and(makePolicy()->reply(OWNER, $ticket))->toBeTrue();
});
