<?php

declare(strict_types=1);

namespace Uzhlaravel\TelegramSystem\Telegram;

use Illuminate\Contracts\Config\Repository as Config;
use RuntimeException;
use Uzhlaravel\TelegramSystem\Contracts\TicketRepositoryInterface;
use Uzhlaravel\TelegramSystem\DTOs\TelegramMessageData;
use Uzhlaravel\TelegramSystem\Tickets\Ticket;
use Uzhlaravel\TelegramSystem\Tickets\TicketMessage;
use Uzhlaravel\TelegramSystem\Tickets\TicketStatus;

/**
 * The direct-message support bridge: the "DM the bot, talk to a human" flow
 * most applications end up writing by hand, packaged once.
 *
 * Unlike the forum-topic model, this needs no forum-enabled supergroup. Each
 * contact's conversation is anchored by a compact "header" message in the
 * support group; the contact's messages are copied beneath it and an agent's
 * replies (posted as Telegram replies to any of the ticket's group messages)
 * are copied straight back into the contact's private chat.
 *
 * It is the Telegram-side mirror of {@see WebChatService}: both reuse the same
 * header-threading convention so a single support group hosts web and Telegram
 * tickets side by side.
 */
final readonly class SupportBridge
{
    public function __construct(
        private MultiBotManager $bots,
        private TicketRepositoryInterface $tickets,
        private Config $config,
    ) {}

    public function enabled(): bool
    {
        return (bool) $this->config->get('telegramsystem.support_bridge.enabled', true);
    }

    /**
     * The configured bot the bridge routes through (its chat_id is the support
     * group the tickets are mirrored into).
     */
    public function bot(): string
    {
        return (string) $this->config->get('telegramsystem.support_bridge.bot', 'support');
    }

    /**
     * Handle a private-chat message from a contact: greet on /start, otherwise
     * open (or continue) their ticket and copy the message into the group.
     */
    public function handleContactMessage(string $bot, TelegramMessageData $message): ?Ticket
    {
        $contactId = $message->fromId;

        if ($contactId === null) {
            return null;
        }

        $text = $message->text ?? '';

        // /start (optionally with a deep-link payload) → welcome, no ticket.
        if ($this->isCommand($text, 'start')) {
            $this->bots->sendMessage($bot, (string) $contactId, $this->template('welcome'), $this->sendOptions());

            return null;
        }

        $groupChatId = $this->bots->chatId($bot);

        if ($groupChatId === null) {
            throw new RuntimeException("Support bridge bot [{$bot}] has no chat_id configured.");
        }

        $name = $message->displayName() ?? $message->fromUsername ?? 'Customer';

        $ticket = $this->tickets->findActiveByOwner($bot, $groupChatId, $contactId)
            ?? $this->openTicket($bot, $groupChatId, $contactId, $message->fromUsername, $name);

        // Anchor the conversation in the group, lazily, the first time.
        $header = $ticket->headerMessage()
            ?? $this->postHeader($bot, $ticket, $message->fromUsername, $name);

        $options = $header->group_message_id !== null
            ? ['reply_to_message_id' => $header->group_message_id]
            : [];

        $copy = $this->bots->copyMessage($bot, $groupChatId, $message->chatId, $message->messageId, $options);

        $ticket->messages()->create([
            'group_message_id' => $this->messageId($copy),
            'direction' => TicketMessage::DIRECTION_FROM_USER,
            'content' => $message->text,
        ]);

        $this->tickets->updateStatus($ticket, TicketStatus::Pending);
        $this->tickets->touchLastMessage($ticket);

        // Acknowledge receipt to the contact.
        $this->bots->sendMessage(
            $bot,
            (string) $contactId,
            $this->template('received', $ticket),
            $this->sendOptions(),
        );

        return $ticket;
    }

    /**
     * Handle an agent's reply inside the support group. Returns true when the
     * reply belonged to a Telegram ticket this bridge owns (and was handled),
     * false when it should fall through to the package's other flows.
     */
    public function handleAgentReply(string $bot, TelegramMessageData $message): bool
    {
        $repliedTo = $message->replyToMessageId;

        if ($repliedTo === null) {
            return false;
        }

        $reference = TicketMessage::query()
            ->where('group_message_id', $repliedTo)
            ->first();

        if ($reference === null) {
            return false;
        }

        $ticket = $this->tickets->find($reference->ticket_id);

        // The bridge only owns Telegram-source tickets that have a private-chat
        // contact to copy the answer back to. Web tickets are captured earlier.
        if ($ticket === null || $ticket->isWeb() || $ticket->owner_id === null) {
            return false;
        }

        $text = $message->text ?? '';

        if ($this->isCommand($text, 'close')) {
            $this->closeTicket($bot, $ticket, $message);

            return true;
        }

        if ($ticket->status->isClosed()) {
            $this->bots->sendMessage(
                $bot,
                $message->chatId,
                $this->template('reply_to_closed', $ticket),
                $this->sendOptions(['reply_to_message_id' => $message->messageId]),
            );

            return true;
        }

        // Copy the agent's reply straight into the contact's private chat.
        $this->bots->copyMessage($bot, (string) $ticket->owner_id, $message->chatId, $message->messageId);

        // Record the first responder (atomic) and reflect that the contact is
        // now the one being waited on.
        if ($ticket->agent_id === null && $message->fromId !== null) {
            $this->tickets->assignAgent($ticket, $message->fromId, $message->fromUsername);
        } else {
            $this->tickets->updateStatus($ticket, TicketStatus::Assigned);
        }

        $this->tickets->touchLastMessage($ticket);

        return true;
    }

    /**
     * Close a ticket from a group "/close" reply: notify the contact and
     * confirm in the group.
     */
    private function closeTicket(string $bot, Ticket $ticket, TelegramMessageData $message): void
    {
        $replyOptions = $this->sendOptions(['reply_to_message_id' => $message->messageId]);

        if ($ticket->status->isClosed()) {
            $this->bots->sendMessage($bot, $message->chatId, $this->template('already_closed', $ticket), $replyOptions);

            return;
        }

        $this->tickets->updateStatus($ticket, TicketStatus::Closed);

        if ($ticket->owner_id !== null) {
            $this->bots->sendMessage($bot, (string) $ticket->owner_id, $this->template('closed', $ticket), $this->sendOptions());
        }

        $this->bots->sendMessage($bot, $message->chatId, $this->template('group_closed', $ticket), $replyOptions);
    }

    private function openTicket(string $bot, string $groupChatId, int $contactId, ?string $username, string $name): Ticket
    {
        return $this->tickets->create([
            'bot' => $bot,
            'source' => Ticket::SOURCE_TELEGRAM,
            'chat_id' => $groupChatId,
            'owner_id' => $contactId,
            'owner_username' => $username ?? $name,
            'status' => TicketStatus::Open,
        ]);
    }

    private function postHeader(string $bot, Ticket $ticket, ?string $username, string $name): TicketMessage
    {
        $sent = $this->bots->sendMessage(
            $bot,
            $ticket->chat_id,
            $this->template('header', $ticket, $name, $username),
            $this->sendOptions(),
        );

        return $ticket->messages()->create([
            'group_message_id' => $this->messageId($sent),
            'direction' => TicketMessage::DIRECTION_HEADER,
            'content' => null,
        ]);
    }

    /**
     * Resolve a configured message template, substituting ticket/contact
     * placeholders (:ticket, :name, :user).
     */
    private function template(string $key, ?Ticket $ticket = null, ?string $name = null, ?string $username = null): string
    {
        $text = (string) $this->config->get("telegramsystem.messages.{$key}", '');

        $user = $username !== null
            ? '@'.$username
            : ($name !== null ? '<b>'.e($name).'</b>' : '');

        return strtr($text, [
            ':ticket' => $ticket instanceof Ticket ? (string) $ticket->ticket_number : '',
            ':name' => $name !== null ? e($name) : '',
            ':user' => $user,
        ]);
    }

    /**
     * Build send options, applying the bridge's configured parse mode for the
     * package-generated text (headers and system notes).
     *
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function sendOptions(array $extra = []): array
    {
        $parseMode = (string) $this->config->get('telegramsystem.support_bridge.parse_mode', 'HTML');

        if ($parseMode !== '') {
            $extra['parse_mode'] = $parseMode;
        }

        return $extra;
    }

    /**
     * Whether the text is the given slash command, tolerating a trailing
     * "@botname" mention and any arguments (e.g. "/start payload").
     */
    private function isCommand(string $text, string $name): bool
    {
        $first = explode(' ', trim($text))[0];
        $first = explode('@', $first)[0];

        return mb_strtolower($first) === '/'.$name;
    }

    /**
     * Pull the Telegram message id out of a send/copy response, tolerating both
     * the unwrapped client shape and the raw {result: {...}} envelope.
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
