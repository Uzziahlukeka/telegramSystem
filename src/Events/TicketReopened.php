<?php

declare(strict_types=1);

namespace Uzhlaravel\TelegramSystem\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Uzhlaravel\TelegramSystem\Tickets\Ticket;

final class TicketReopened
{
    use Dispatchable;

    public function __construct(
        public readonly Ticket $ticket,
        public readonly int $reopenedBy,
    ) {}
}
