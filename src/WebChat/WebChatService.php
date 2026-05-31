<?php

declare(strict_types=1);

namespace Uzhlaravel\TelegramSystem\WebChat;

use Illuminate\Contracts\Config\Repository as Config;
use RuntimeException;
use Uzhlaravel\TelegramSystem\Contracts\TicketRepositoryInterface;
use Uzhlaravel\TelegramSystem\DTOs\TelegramMessageData;
use Uzhlaravel\TelegramSystem\Telegram\MultiBotManager;
use Uzhlaravel\TelegramSystem\Tickets\Ticket;
use Uzhlaravel\TelegramSystem\Tickets\TicketMessage;
use Uzhlaravel\TelegramSystem\Tickets\TicketStatus;

/**
 * The brains behind the web-chat widget. It mirrors a website visitor's
 * conversation into a Telegram support group and replays the agents' replies
 * back to the browser.
 *
 * A web ticket has no Telegram owner; it is correlated to the browser by a
 * session token. Every visitor message is posted into the group threaded under
 * the ticket's "header" message, and every inbound reply to one of those group
 * messages is captured back as an agent reply (see {@see captureAgentReply()}),
 * which is how the webhook links Telegram answers to the right ticket.
 */
final readonly class WebChatService
{
    public function __construct(
        private MultiBotManager $bots,
        private TicketRepositoryInterface $tickets,
        private Config $config,
    ) {}

    public function enabled(): bool
    {
        return (bool) $this->config->get('telegramsystem.web_chat.enabled', true);
    }

    /**
     * The configured bot the web chat routes through (its chat_id is the
     * support group, and its topic_id — if any — the support topic).
     */
    public function bot(): string
    {
        return (string) $this->config->get('telegramsystem.web_chat.bot', 'support');
    }

    /**
     * The latest web ticket bound to a browser session token, if any.
     */
    public function ticketForSession(string $token): ?Ticket
    {
        if ($token === '') {
            return null;
        }

        return Ticket::query()
            ->where('source', Ticket::SOURCE_WEB)
            ->where('web_session_token', $token)
            ->get()
            ->sortByDesc('id')
            ->first();
    }

    /**
     * Post a visitor message to Telegram, opening the ticket on first contact.
     * Returns the (created or existing) web ticket.
     */
    public function send(string $sessionToken, string $name, ?string $email, string $message): Ticket
    {
        $bot = $this->bot();
        $chatId = $this->bots->chatId($bot);

        if ($chatId === null) {
            throw new RuntimeException("Web chat bot [{$bot}] has no chat_id configured.");
        }

        $email = ($email !== null && trim($email) !== '') ? trim($email) : null;
        $topicId = $this->bots->topicId($bot);
        $baseOptions = $topicId !== null ? ['message_thread_id' => $topicId] : [];

        $ticket = $this->ticketForSession($sessionToken)
            ?? $this->openTicket($bot, $chatId, $name, $email, $sessionToken, $baseOptions);

        $options = $baseOptions;
        $header = $ticket->headerMessage();

        if ($header !== null && $header->group_message_id !== null) {
            $options['reply_to_message_id'] = $header->group_message_id;
        }

        $result = $this->bots->sendMessage($bot, $ticket->chat_id, $message, $options);

        $ticket->messages()->create([
            'group_message_id' => $this->messageId($result),
            'direction' => TicketMessage::DIRECTION_FROM_USER,
            'content' => $message,
        ]);

        $this->tickets->updateStatus($ticket, TicketStatus::Pending);
        $this->tickets->touchLastMessage($ticket);

        return $ticket;
    }

    /**
     * The visible conversation (visitor + agent lines) ready for the widget.
     *
     * @return list<array{direction: string, content: string, time: string}>
     */
    public function conversation(Ticket $ticket): array
    {
        return $ticket->messages()
            ->get()
            ->whereIn('direction', [
                TicketMessage::DIRECTION_FROM_USER,
                TicketMessage::DIRECTION_FROM_AGENT,
            ])
            ->sortBy('id')
            ->map(static fn (TicketMessage $m): array => [
                'direction' => $m->direction,
                'content' => $m->content ?? '',
                'time' => $m->created_at->diffForHumans(),
            ])
            ->values()
            ->all();
    }

    /**
     * Link an inbound Telegram reply to the web ticket whose group message it
     * answered, recording it as an agent reply. Returns null when the reply is
     * not for a web ticket (so the normal Telegram flow handles it).
     */
    public function captureAgentReply(int $repliedToGroupMessageId, TelegramMessageData $reply): ?TicketMessage
    {
        if (! $reply->hasText()) {
            return null;
        }

        $reference = TicketMessage::query()
            ->where('group_message_id', $repliedToGroupMessageId)
            ->first();

        if ($reference === null) {
            return null;
        }

        $ticket = $this->tickets->find($reference->ticket_id);

        if ($ticket === null || ! $ticket->isWeb()) {
            return null;
        }

        // Idempotency: the same group reply must not be recorded twice.
        $existing = $ticket->messages()
            ->where('direction', TicketMessage::DIRECTION_FROM_AGENT)
            ->where('group_message_id', $reply->messageId)
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        $recorded = $ticket->messages()->create([
            'group_message_id' => $reply->messageId,
            'direction' => TicketMessage::DIRECTION_FROM_AGENT,
            'content' => $reply->text,
        ]);

        if ($ticket->status->isActive()) {
            $this->tickets->updateStatus($ticket, TicketStatus::Assigned);
        }

        $this->tickets->touchLastMessage($ticket);

        return $recorded;
    }

    /**
     * Create a web ticket and announce it to Telegram with a header message the
     * subsequent visitor and agent messages thread under.
     *
     * @param  array<string, mixed>  $baseOptions
     */
    private function openTicket(
        string $bot,
        string $chatId,
        string $name,
        ?string $email,
        string $sessionToken,
        array $baseOptions,
    ): Ticket {
        $ticket = $this->tickets->create([
            'bot' => $bot,
            'source' => Ticket::SOURCE_WEB,
            'chat_id' => $chatId,
            'owner_id' => null,
            'owner_username' => $name,
            'web_session_token' => $sessionToken,
            'web_email' => $email,
            'status' => TicketStatus::Open,
        ]);

        $contact = $email !== null
            ? e($name).' ('.e($email).')'
            : e($name);

        $header = "💬 <b>Web Chat — Ticket {$ticket->ticket_number}</b>\n"
            ."👤 {$contact}\n"
            .'─────────────';

        $sent = $this->bots->sendMessage($bot, $chatId, $header, $baseOptions);

        $ticket->messages()->create([
            'group_message_id' => $this->messageId($sent),
            'direction' => TicketMessage::DIRECTION_HEADER,
            'content' => null,
        ]);

        return $ticket;
    }

    /**
     * Pull the Telegram message id out of a send response, tolerating both the
     * unwrapped client shape and the raw {result: {...}} envelope.
     *
     * @param  array<string, mixed>|bool  $response
     */
    private function messageId(array|bool $response): ?int
    {
        if (! is_array($response)) {
            return null;
        }

        if (isset($response['message_id'])) {
            return (int) $response['message_id'];
        }

        if (isset($response['result']) && is_array($response['result']) && isset($response['result']['message_id'])) {
            return (int) $response['result']['message_id'];
        }

        return null;
    }
}
