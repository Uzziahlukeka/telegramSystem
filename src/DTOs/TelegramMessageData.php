<?php

declare(strict_types=1);

namespace Uzhlaravel\TelegramSystem\DTOs;

/**
 * A typed view over a Telegram "message" object. Raw arrays never travel
 * through the domain layer; they are normalised here first.
 */
final readonly class TelegramMessageData
{
    public function __construct(
        public int $messageId,
        public string $chatId,
        public string $chatType,
        public ?int $fromId,
        public ?string $fromUsername,
        public bool $fromIsBot,
        public ?int $messageThreadId,
        public ?int $replyToMessageId,
        public ?string $text,
        public bool $isTopicMessage,
        public bool $isForumGroup,
        public ?string $fromFirstName = null,
        public ?string $fromLastName = null,
    ) {}

    /**
     * @param  array<string, mixed>  $payload  A Telegram "message" object.
     */
    public static function fromResponse(array $payload): self
    {
        /** @var array<string, mixed> $chat */
        $chat = is_array($payload['chat'] ?? null) ? $payload['chat'] : [];

        /** @var array<string, mixed> $from */
        $from = is_array($payload['from'] ?? null) ? $payload['from'] : [];

        /** @var array<string, mixed> $replyTo */
        $replyTo = is_array($payload['reply_to_message'] ?? null) ? $payload['reply_to_message'] : [];

        return new self(
            messageId: (int) ($payload['message_id'] ?? 0),
            chatId: (string) ($chat['id'] ?? ''),
            chatType: (string) ($chat['type'] ?? ''),
            fromId: isset($from['id']) ? (int) $from['id'] : null,
            fromUsername: isset($from['username']) ? (string) $from['username'] : null,
            fromIsBot: (bool) ($from['is_bot'] ?? false),
            messageThreadId: isset($payload['message_thread_id']) ? (int) $payload['message_thread_id'] : null,
            replyToMessageId: isset($replyTo['message_id']) ? (int) $replyTo['message_id'] : null,
            // Fall back to a media caption so photo/document replies still carry
            // a body the bridge can act on (command detection, web replay).
            text: isset($payload['text'])
                ? (string) $payload['text']
                : (isset($payload['caption']) ? (string) $payload['caption'] : null),
            isTopicMessage: (bool) ($payload['is_topic_message'] ?? false),
            isForumGroup: (bool) ($chat['is_forum'] ?? false),
            fromFirstName: isset($from['first_name']) ? (string) $from['first_name'] : null,
            fromLastName: isset($from['last_name']) ? (string) $from['last_name'] : null,
        );
    }

    public function hasText(): bool
    {
        return $this->text !== null && trim($this->text) !== '';
    }

    /**
     * The sender's display name from first/last name, when available.
     */
    public function displayName(): ?string
    {
        $name = trim(($this->fromFirstName ?? '').' '.($this->fromLastName ?? ''));

        return $name !== '' ? $name : null;
    }
}
