<?php

declare(strict_types=1);

namespace Uzhlaravel\TelegramSystem\Facades;

use Illuminate\Support\Facades\Facade;
use Uzhlaravel\Telegramlogs\ActivityLogger;
use Uzhlaravel\TelegramSystem\Contracts\TicketRepositoryInterface;
use Uzhlaravel\TelegramSystem\Telegram\MultiBotManager;
use Uzhlaravel\TelegramSystem\Telegram\TelegramlogsBridge;
use Uzhlaravel\TelegramSystem\Tickets\Ticket;
use Uzhlaravel\TelegramSystem\WebChat\WebChatService;

/**
 * @method static MultiBotManager bots()
 * @method static TicketRepositoryInterface tickets()
 * @method static TelegramlogsBridge telegramlogs()
 * @method static WebChatService webChat()
 * @method static Ticket openTicket(string $bot, string $chatId, int $ownerId, ?string $ownerUsername = null, ?string $subject = null)
 * @method static Ticket assignAgent(Ticket $ticket, int $agentId, ?string $agentUsername = null)
 * @method static Ticket close(Ticket $ticket, int $actorId)
 * @method static Ticket reopen(Ticket $ticket, int $actorId)
 * @method static array<string, mixed>|bool send(?string $bot, string $chatId, string $text, array $options = [])
 * @method static array<string, mixed>|bool message(string $text)
 * @method static array<string, mixed>|bool toChat(string $chatId, string $text, array $options = [])
 * @method static ActivityLogger activity()
 * @method static void log(string $level, string $message, array $context = [])
 *
 * @see \Uzhlaravel\TelegramSystem\TelegramSystem
 */
class TelegramSystem extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Uzhlaravel\TelegramSystem\TelegramSystem::class;
    }
}
