<?php

declare(strict_types=1);

namespace Uzhlaravel\TelegramSystem\Telegram;

use Illuminate\Contracts\Config\Repository as Config;
use Uzhlaravel\TelegramSystem\Actions\AssignTicketAction;
use Uzhlaravel\TelegramSystem\Actions\CreateTicketAction;
use Uzhlaravel\TelegramSystem\Contracts\TicketRepositoryInterface;
use Uzhlaravel\TelegramSystem\DTOs\TelegramMessageData;
use Uzhlaravel\TelegramSystem\DTOs\UpdateData;
use Uzhlaravel\TelegramSystem\Exceptions\TelegramApiException;
use Uzhlaravel\TelegramSystem\Exceptions\UnauthorizedTicketAccessException;
use Uzhlaravel\TelegramSystem\Tickets\Ticket;
use Uzhlaravel\TelegramSystem\Tickets\TicketPolicy;
use Uzhlaravel\TelegramSystem\WebChat\WebChatService;

/**
 * Turns an inbound {@see UpdateData} (from a webhook or long polling) into
 * ticket-domain actions for a specific bot:
 *
 *  - A private-chat message on the support-bridge bot opens/continues a
 *    direct-message ticket (no forum topic required).
 *  - A reply to a web-chat ticket's group message is captured back into the
 *    web widget as an agent reply.
 *  - A reply to a Telegram DM-ticket's group message is copied back into the
 *    contact's private chat (the support-bridge agent flow).
 *  - A non-admin message with no matching ticket opens one.
 *  - A reply to an existing ticket is authorized (policy), and the first
 *    eligible non-admin replier becomes the assigned agent.
 *  - Messages from users with no business in the ticket are rejected (and,
 *    when configured, deleted) — the application-layer enforcement Telegram
 *    itself cannot provide.
 */
final class WebhookHandler
{
    public function __construct(
        private readonly TicketRepositoryInterface $tickets,
        private readonly MultiBotManager $bots,
        private readonly TicketPolicy $policy,
        private readonly CreateTicketAction $createTicket,
        private readonly AssignTicketAction $assignTicket,
        private readonly WebChatService $webChat,
        private readonly SupportBridge $bridge,
        private readonly Config $config,
    ) {}

    /**
     * @throws UnauthorizedTicketAccessException When an unauthorized user posts
     *                                           into an existing ticket.
     */
    public function handle(string $bot, UpdateData $update): void
    {
        $message = $update->message;

        if ($message === null || $message->fromIsBot || ! $message->hasText()) {
            return;
        }

        $fromId = $message->fromId;

        if ($fromId === null) {
            return;
        }

        $bridgeActive = $this->bridge->enabled() && $bot === $this->bridge->bot();

        // Support bridge: a contact direct-messaging the bot opens/continues a
        // ticket without any forum topic. Private chats never reach the
        // forum-topic flow, so this is purely additive.
        if ($bridgeActive && $message->chatType === 'private') {
            $this->bridge->handleContactMessage($bot, $message);

            return;
        }

        // Web chat: an agent replying to a web ticket's group message threads
        // the answer straight back to the browser. Handled before the Telegram
        // ticket flow because web tickets carry no forum topic to resolve.
        if ($message->replyToMessageId !== null
            && $this->webChat->captureAgentReply($message->replyToMessageId, $message) !== null) {
            return;
        }

        // Support bridge: an agent replying to a Telegram DM-ticket's group
        // message is copied straight back into the contact's private chat
        // (and "/close" closes the ticket).
        if ($bridgeActive
            && $message->replyToMessageId !== null
            && $this->bridge->handleAgentReply($bot, $message)) {
            return;
        }

        $ticket = $this->resolveTicket($bot, $message);

        if ($ticket === null) {
            $this->openTicketIfContact($bot, $message, $fromId);

            return;
        }

        // Authorization layer 2 + 3: reject anyone who may not post here.
        if (! $this->policy->reply($fromId, $ticket)) {
            $this->reject($ticket, $message, $fromId);
        }

        // First-reply assignment (no-op for owner/admins/already-assigned).
        $ticket = $this->assignTicket->execute($ticket, $fromId, $message->fromUsername);

        $this->tickets->touchLastMessage($ticket);
    }

    private function resolveTicket(string $bot, TelegramMessageData $message): ?Ticket
    {
        if ($message->messageThreadId !== null) {
            return $this->tickets->findByThread($bot, $message->chatId, $message->messageThreadId);
        }

        return null;
    }

    private function openTicketIfContact(string $bot, TelegramMessageData $message, int $fromId): void
    {
        // Admin chatter outside any ticket is ignored; a non-admin opens a ticket.
        if ($this->policy->isAdmin($fromId)) {
            return;
        }

        $ticket = $this->createTicket->execute(
            $bot,
            $message->chatId,
            $fromId,
            $message->fromUsername,
        );

        $this->tickets->touchLastMessage($ticket);
    }

    /**
     * @throws UnauthorizedTicketAccessException
     */
    private function reject(Ticket $ticket, TelegramMessageData $message, int $fromId): never
    {
        if ((bool) $this->config->get('telegramsystem.enforcement.delete_unauthorized', false)) {
            try {
                $this->bots->forTicket($ticket)->deleteMessage($message->chatId, $message->messageId);
            } catch (TelegramApiException) {
                // Best-effort deletion; never mask the authorization failure.
            }
        }

        throw UnauthorizedTicketAccessException::for($fromId, $ticket, 'reply');
    }
}
