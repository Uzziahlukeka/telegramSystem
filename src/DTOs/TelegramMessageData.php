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
        public ?string $text,
        public bool $isTopicMessage,
        public bool $isForumGroup,
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

        return new self(
            messageId: (int) ($payload['message_id'] ?? 0),
            chatId: (string) ($chat['id'] ?? ''),
            chatType: (string) ($chat['type'] ?? ''),
            fromId: isset($from['id']) ? (int) $from['id'] : null,
            fromUsername: isset($from['username']) ? (string) $from['username'] : null,
            fromIsBot: (bool) ($from['is_bot'] ?? false),
            messageThreadId: isset($payload['message_thread_id']) ? (int) $payload['message_thread_id'] : null,
            text: isset($payload['text']) ? (string) $payload['text'] : null,
            isTopicMessage: (bool) ($payload['is_topic_message'] ?? false),
            isForumGroup: (bool) ($chat['is_forum'] ?? false),
        );
    }

    public function hasText(): bool
    {
        return $this->text !== null && trim($this->text) !== '';
    }
}
