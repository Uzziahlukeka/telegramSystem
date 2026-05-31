<?php

declare(strict_types=1);

namespace Uzhlaravel\TelegramSystem\Telegram;

use Illuminate\Contracts\Config\Repository as Config;
use Uzhlaravel\TelegramSystem\DTOs\ForumTopicData;
use Uzhlaravel\TelegramSystem\Exceptions\TelegramApiException;
use Uzhlaravel\TelegramSystem\Tickets\Ticket;
use Uzhlaravel\TelegramSystem\Tickets\TicketStatus;

/**
 * Owns forum-topic lifecycle. When topics are disabled, or the target group is
 * not a forum, every method degrades gracefully so the ticket simply lives in
 * the main chat instead.
 */
final class TopicManager
{
    public function __construct(
        private readonly MultiBotManager $bots,
        private readonly Config $config,
    ) {}

    public function enabled(): bool
    {
        return (bool) $this->config->get('telegramsystem.topics.enabled', true);
    }

    /**
     * Attempt to create a forum topic for a ticket. Returns null when topics
     * are disabled or the group is not a forum (fallback to main chat).
     */
    public function createForTicket(string $bot, string $chatId, string $name): ?ForumTopicData
    {
        if (! $this->enabled()) {
            return null;
        }

        try {
            return $this->bots->client($bot)->createForumTopic($chatId, $name);
        } catch (TelegramApiException) {
            // Not a forum / bot lacks "Manage Topics" rights: fall back gracefully.
            return null;
        }
    }

    /**
     * Mirror the ticket status onto its forum topic (close/reopen), when enabled.
     */
    public function syncStatus(Ticket $ticket): void
    {
        if (! $this->shouldSync($ticket)) {
            return;
        }

        $threadId = $ticket->message_thread_id;

        if ($threadId === null) {
            return;
        }

        try {
            if ($ticket->status->isClosed()) {
                $this->bots->forTicket($ticket)->closeForumTopic($ticket->chat_id, $threadId);

                return;
            }

            if ($ticket->status === TicketStatus::Reopened) {
                $this->bots->forTicket($ticket)->reopenForumTopic($ticket->chat_id, $threadId);
            }
        } catch (TelegramApiException) {
            // Topic sync is best-effort; never let it break the ticket flow.
        }
    }

    private function shouldSync(Ticket $ticket): bool
    {
        return $this->enabled()
            && (bool) $this->config->get('telegramsystem.topics.sync_status', true)
            && $ticket->message_thread_id !== null;
    }
}
