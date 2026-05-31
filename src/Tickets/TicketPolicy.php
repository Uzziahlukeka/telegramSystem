<?php

declare(strict_types=1);

namespace Uzhlaravel\TelegramSystem\Tickets;

/**
 * Application-layer authorization for tickets (authorization layer 2).
 *
 * Telegram has no native per-topic privacy, so every interaction is checked
 * here against three roles:
 *
 *  - admin: configured Telegram IDs; may view, assign-override, close and
 *           reopen every ticket, but never becomes the assigned agent.
 *  - owner: the non-admin contact who opened the ticket.
 *  - agent: the first non-admin replier, assigned atomically.
 */
final class TicketPolicy
{
    /**
     * @var array<int, int>
     */
    private array $admins;

    /**
     * @param  array<int, int|string>  $admins
     */
    public function __construct(array $admins = [])
    {
        $this->admins = array_map(static fn (int|string $id): int => (int) $id, array_values($admins));
    }

    public function isAdmin(int $telegramUserId): bool
    {
        return in_array($telegramUserId, $this->admins, true);
    }

    /**
     * May the user see this ticket? Admins, the owner and the assigned agent.
     */
    public function view(int $telegramUserId, Ticket $ticket): bool
    {
        return $this->isAdmin($telegramUserId)
            || $this->isOwner($telegramUserId, $ticket)
            || $this->isAgent($telegramUserId, $ticket);
    }

    /**
     * May the user become the assigned agent? Only a non-admin, non-owner
     * replying into an unassigned, still-active ticket is eligible.
     */
    public function assign(int $telegramUserId, Ticket $ticket): bool
    {
        if ($this->isAdmin($telegramUserId) || $this->isOwner($telegramUserId, $ticket)) {
            return false;
        }

        return $ticket->agent_id === null && $ticket->status->isActive();
    }

    /**
     * May the user post a reply into this ticket?
     *
     * Participants (admin/owner/agent) always may. While a ticket is still
     * unassigned and active, a prospective agent may also reply (which is what
     * claims it); once assigned, strangers are locked out.
     */
    public function reply(int $telegramUserId, Ticket $ticket): bool
    {
        if ($this->view($telegramUserId, $ticket)) {
            return true;
        }

        return $ticket->agent_id === null && $ticket->status->isActive();
    }

    /**
     * May the user close this ticket? Admins and the owner.
     */
    public function close(int $telegramUserId, Ticket $ticket): bool
    {
        return $this->isAdmin($telegramUserId) || $this->isOwner($telegramUserId, $ticket);
    }

    /**
     * May the user reopen this ticket? Admins and the owner.
     */
    public function reopen(int $telegramUserId, Ticket $ticket): bool
    {
        return $this->isAdmin($telegramUserId) || $this->isOwner($telegramUserId, $ticket);
    }

    private function isOwner(int $telegramUserId, Ticket $ticket): bool
    {
        return $ticket->owner_id === $telegramUserId;
    }

    private function isAgent(int $telegramUserId, Ticket $ticket): bool
    {
        return $ticket->agent_id !== null && $ticket->agent_id === $telegramUserId;
    }
}
