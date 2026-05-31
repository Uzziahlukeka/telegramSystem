<?php

declare(strict_types=1);

namespace Uzhlaravel\TelegramSystem\Actions;

use Illuminate\Contracts\Events\Dispatcher;
use Uzhlaravel\TelegramSystem\Contracts\TicketRepositoryInterface;
use Uzhlaravel\TelegramSystem\Events\TicketCreated;
use Uzhlaravel\TelegramSystem\Exceptions\UnauthorizedTicketAccessException;
use Uzhlaravel\TelegramSystem\Telegram\TopicManager;
use Uzhlaravel\TelegramSystem\Tickets\Ticket;
use Uzhlaravel\TelegramSystem\Tickets\TicketPolicy;
use Uzhlaravel\TelegramSystem\Tickets\TicketStatus;

/**
 * Opens a ticket for a non-admin contact, creating a forum topic when possible.
 * If the contact already has an active ticket in the chat, that ticket is
 * returned instead of creating a duplicate (rule: one owner per ticket).
 */
final class CreateTicketAction
{
    public function __construct(
        private readonly TicketRepositoryInterface $tickets,
        private readonly TopicManager $topics,
        private readonly TicketPolicy $policy,
        private readonly Dispatcher $events,
    ) {}

    public function execute(
        string $bot,
        string $chatId,
        int $ownerId,
        ?string $ownerUsername = null,
        ?string $subject = null,
    ): Ticket {
        // Rule 1: a ticket's owner must be a non-admin contact.
        if ($this->policy->isAdmin($ownerId)) {
            throw new UnauthorizedTicketAccessException(
                telegramUserId: $ownerId,
                ability: 'open',
                message: "Admin [{$ownerId}] cannot be the contact/owner of a ticket.",
            );
        }

        $existing = $this->tickets->findActiveByOwner($bot, $chatId, $ownerId);

        if ($existing !== null) {
            return $existing;
        }

        $topic = $this->topics->createForTicket(
            $bot,
            $chatId,
            $subject ?? $this->defaultTopicName($ownerId, $ownerUsername),
        );

        $ticket = $this->tickets->create([
            'bot' => $bot,
            'chat_id' => $chatId,
            'message_thread_id' => $topic?->messageThreadId,
            'owner_id' => $ownerId,
            'owner_username' => $ownerUsername,
            'subject' => $subject,
            'status' => TicketStatus::Open,
        ]);

        $this->events->dispatch(new TicketCreated($ticket));

        return $ticket;
    }

    private function defaultTopicName(int $ownerId, ?string $ownerUsername): string
    {
        return $ownerUsername !== null
            ? "Ticket · @{$ownerUsername}"
            : "Ticket · {$ownerId}";
    }
}
