<?php

declare(strict_types=1);

use Uzhlaravel\TelegramSystem\Tickets\TicketStatus;

it('is a backed enum with the documented cases', function () {
    expect(TicketStatus::Open->value)->toBe('open')
        ->and(TicketStatus::Pending->value)->toBe('pending')
        ->and(TicketStatus::Assigned->value)->toBe('assigned')
        ->and(TicketStatus::Closed->value)->toBe('closed')
        ->and(TicketStatus::Reopened->value)->toBe('reopened');
});

it('knows whether it is closed or active', function () {
    expect(TicketStatus::Closed->isClosed())->toBeTrue()
        ->and(TicketStatus::Closed->isActive())->toBeFalse()
        ->and(TicketStatus::Open->isActive())->toBeTrue();
});

it('exposes the active/open states', function () {
    expect(TicketStatus::openStates())->not->toContain(TicketStatus::Closed)
        ->and(TicketStatus::openStates())->toContain(TicketStatus::Open);
});
