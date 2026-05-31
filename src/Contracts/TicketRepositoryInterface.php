<?php

declare(strict_types=1);

namespace Uzhlaravel\TelegramSystem\Contracts;

use Illuminate\Support\Collection;
use Uzhlaravel\TelegramSystem\Tickets\Ticket;
use Uzhlaravel\TelegramSystem\Tickets\TicketStatus;

/**
 * All ticket reads and writes go through this abstraction. The default
 * implementation is Eloquent-backed, but consumers may swap it out.
 */
interface TicketRepositoryInterface
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): Ticket;

    public function find(int $id): ?Ticket;

    /**
     * Find a ticket by the forum topic it is routed into.
     */
    public function findByThread(string $bot, string $chatId, int $messageThreadId): ?Ticket;

    /**
     * Find an existing non-closed ticket owned by a Telegram user in a chat.
     */
    public function findActiveByOwner(string $bot, string $chatId, int $ownerId): ?Ticket;

    /**
     * Atomically assign an agent. Returns true only for the caller that won
     * the race (i.e. the first reply); false if an agent was already set.
     */
    public function assignAgent(Ticket $ticket, int $agentId, ?string $agentUsername): bool;

    public function updateStatus(Ticket $ticket, TicketStatus $status): bool;

    public function touchLastMessage(Ticket $ticket): bool;

    /**
     * Tickets visible to a Telegram user (scope/query authorization layer).
     *
     * @return Collection<int, Ticket>
     */
    public function visibleTo(int $telegramUserId, bool $isAdmin = false): Collection;
}
