<?php

declare(strict_types=1);

namespace Uzhlaravel\TelegramSystem;

use Uzhlaravel\Telegramlogs\ActivityLogger;
use Uzhlaravel\TelegramSystem\Actions\AssignTicketAction;
use Uzhlaravel\TelegramSystem\Actions\CloseTicketAction;
use Uzhlaravel\TelegramSystem\Actions\CreateTicketAction;
use Uzhlaravel\TelegramSystem\Contracts\TicketRepositoryInterface;
use Uzhlaravel\TelegramSystem\Telegram\MultiBotManager;
use Uzhlaravel\TelegramSystem\Telegram\SupportBridge;
use Uzhlaravel\TelegramSystem\Telegram\TelegramlogsBridge;
use Uzhlaravel\TelegramSystem\Tickets\Ticket;
use Uzhlaravel\TelegramSystem\WebChat\WebChatService;

/**
 * The public entry point behind the TelegramSystem facade. It is a thin
 * coordinator over the repository, the action classes and the multi-bot
 * manager so that consumers have one fluent surface to work against.
 */
final class TelegramSystem
{
    public function __construct(
        private readonly MultiBotManager $bots,
        private readonly TicketRepositoryInterface $tickets,
        private readonly CreateTicketAction $createTicket,
        private readonly AssignTicketAction $assignTicket,
        private readonly CloseTicketAction $closeTicket,
        private readonly TelegramlogsBridge $telegramlogs,
        private readonly WebChatService $webChat,
        private readonly SupportBridge $supportBridge,
    ) {}

    public function bots(): MultiBotManager
    {
        return $this->bots;
    }

    /**
     * The web-chat widget service (open web tickets, replay agent replies).
     */
    public function webChat(): WebChatService
    {
        return $this->webChat;
    }

    /**
     * The direct-message support bridge (DM-the-bot ↔ support-group tickets).
     */
    public function supportBridge(): SupportBridge
    {
        return $this->supportBridge;
    }

    /**
     * The telegramlogs bridge (logging, direct messaging, activity notifications).
     */
    public function telegramlogs(): TelegramlogsBridge
    {
        return $this->telegramlogs;
    }

    public function tickets(): TicketRepositoryInterface
    {
        return $this->tickets;
    }

    public function openTicket(
        string $bot,
        string $chatId,
        int $ownerId,
        ?string $ownerUsername = null,
        ?string $subject = null,
    ): Ticket {
        return $this->createTicket->execute($bot, $chatId, $ownerId, $ownerUsername, $subject);
    }

    public function assignAgent(Ticket $ticket, int $agentId, ?string $agentUsername = null): Ticket
    {
        return $this->assignTicket->execute($ticket, $agentId, $agentUsername);
    }

    public function close(Ticket $ticket, int $actorId): Ticket
    {
        return $this->closeTicket->execute($ticket, $actorId);
    }

    public function reopen(Ticket $ticket, int $actorId): Ticket
    {
        return $this->closeTicket->reopen($ticket, $actorId);
    }

    /**
     * Send a message through a bot (default bot delegates to telegramlogs).
     *
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>|bool
     */
    public function send(?string $bot, string $chatId, string $text, array $options = []): array|bool
    {
        return $this->bots->sendMessage($bot, $chatId, $text, $options);
    }

    /*
    |--------------------------------------------------------------------------
    | telegramlogs passthroughs (logging, DM, activity) — never reimplemented
    |--------------------------------------------------------------------------
    */

    /**
     * Direct-message the default bot's configured chat (via telegramlogs).
     *
     * @return array<string, mixed>|bool
     */
    public function message(string $text): array|bool
    {
        return $this->telegramlogs->message($text);
    }

    /**
     * Direct-message a specific chat (via telegramlogs).
     *
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>|bool
     */
    public function toChat(string $chatId, string $text, array $options = []): array|bool
    {
        return $this->telegramlogs->toChat($chatId, $text, $options);
    }

    /**
     * A fluent telegramlogs activity logger.
     */
    public function activity(): ActivityLogger
    {
        return $this->telegramlogs->activity();
    }

    /**
     * Write to the telegramlogs-backed log channel.
     *
     * @param  array<string, mixed>  $context
     */
    public function log(string $level, string $message, array $context = []): void
    {
        $this->telegramlogs->log($level, $message, $context);
    }
}
