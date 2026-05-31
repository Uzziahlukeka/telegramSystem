<?php

declare(strict_types=1);

namespace Uzhlaravel\TelegramSystem\Exceptions;

use RuntimeException;
use Uzhlaravel\TelegramSystem\Tickets\Ticket;

/**
 * Thrown whenever a Telegram user attempts to view or interact with a ticket
 * they are not permitted to access. Raised consistently from the scope layer,
 * the policy, and the action re-checks.
 */
final class UnauthorizedTicketAccessException extends RuntimeException
{
    public function __construct(
        public readonly int $telegramUserId,
        public readonly ?int $ticketId = null,
        public readonly string $ability = 'view',
        string $message = '',
    ) {
        parent::__construct(
            $message !== '' ? $message : sprintf(
                'Telegram user [%d] is not authorized to [%s] ticket [%s].',
                $telegramUserId,
                $ability,
                $ticketId === null ? 'unknown' : (string) $ticketId,
            ),
        );
    }

    public static function for(int $telegramUserId, Ticket $ticket, string $ability): self
    {
        return new self($telegramUserId, $ticket->id, $ability);
    }
}
