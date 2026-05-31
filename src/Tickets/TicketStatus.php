<?php

declare(strict_types=1);

namespace Uzhlaravel\TelegramSystem\Tickets;

/**
 * The lifecycle states a ticket moves through.
 *
 * A ticket is "active" while it still needs attention (open, pending,
 * assigned, reopened) and "closed" once it has been resolved.
 */
enum TicketStatus: string
{
    case Open = 'open';
    case Pending = 'pending';
    case Assigned = 'assigned';
    case Closed = 'closed';
    case Reopened = 'reopened';

    /**
     * The states that count as still-open / active (everything but Closed).
     *
     * @return array<int, self>
     */
    public static function openStates(): array
    {
        return array_values(array_filter(
            self::cases(),
            static fn (self $status): bool => $status !== self::Closed,
        ));
    }

    public function isClosed(): bool
    {
        return $this === self::Closed;
    }

    public function isActive(): bool
    {
        return ! $this->isClosed();
    }

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Open',
            self::Pending => 'Pending',
            self::Assigned => 'Assigned',
            self::Closed => 'Closed',
            self::Reopened => 'Reopened',
        };
    }

    public function emoji(): string
    {
        return match ($this) {
            self::Open => '🟢',
            self::Pending => '🟡',
            self::Assigned => '🔵',
            self::Closed => '🔴',
            self::Reopened => '🟠',
        };
    }
}
