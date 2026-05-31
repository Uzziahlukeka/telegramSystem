<?php

declare(strict_types=1);

namespace Uzhlaravel\TelegramSystem\Repositories;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Uzhlaravel\TelegramSystem\Contracts\TicketRepositoryInterface;
use Uzhlaravel\TelegramSystem\Tickets\Ticket;
use Uzhlaravel\TelegramSystem\Tickets\TicketStatus;

final class EloquentTicketRepository implements TicketRepositoryInterface
{
    public function create(array $attributes): Ticket
    {
        return Ticket::query()->create($attributes);
    }

    public function find(int $id): ?Ticket
    {
        return Ticket::query()->find($id);
    }

    public function findByThread(string $bot, string $chatId, int $messageThreadId): ?Ticket
    {
        return Ticket::query()
            ->where('bot', $bot)
            ->where('chat_id', $chatId)
            ->where('message_thread_id', $messageThreadId)
            ->first();
    }

    public function findActiveByOwner(string $bot, string $chatId, int $ownerId): ?Ticket
    {
        return Ticket::query()
            ->where('bot', $bot)
            ->where('chat_id', $chatId)
            ->where('owner_id', $ownerId)
            ->where(function (Builder $query): void {
                $query->whereIn('status', $this->openStatusValues());
            })
            ->get()
            ->sortByDesc('id')
            ->first();
    }

    public function assignAgent(Ticket $ticket, int $agentId, ?string $agentUsername): bool
    {
        // Atomic guard: the UPDATE only matches while agent_id IS NULL, so just
        // one concurrent caller can flip it. The winner gets a 1-row update.
        $affected = Ticket::query()
            ->whereKey($ticket->getKey())
            ->whereNull('agent_id')
            ->update([
                'agent_id' => $agentId,
                'agent_username' => $agentUsername,
                'status' => TicketStatus::Assigned->value,
            ]);

        return $affected === 1;
    }

    public function updateStatus(Ticket $ticket, TicketStatus $status): bool
    {
        $ticket->status = $status;

        return $ticket->save();
    }

    public function touchLastMessage(Ticket $ticket): bool
    {
        $ticket->last_message_at = Carbon::now();

        return $ticket->save();
    }

    public function visibleTo(int $telegramUserId, bool $isAdmin = false): Collection
    {
        return Ticket::visibleTo($telegramUserId, $isAdmin)
            ->get()
            ->sortByDesc('id')
            ->values();
    }

    /**
     * @return array<int, string>
     */
    private function openStatusValues(): array
    {
        return array_map(
            static fn (TicketStatus $status): string => $status->value,
            TicketStatus::openStates(),
        );
    }
}
