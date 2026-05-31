<?php

declare(strict_types=1);

namespace Uzhlaravel\TelegramSystem\DTOs;

/**
 * A typed view over the result of createForumTopic / a forum_topic object.
 */
final readonly class ForumTopicData
{
    public function __construct(
        public int $messageThreadId,
        public string $name,
        public ?int $iconColor = null,
        public ?string $iconCustomEmojiId = null,
    ) {}

    /**
     * @param  array<string, mixed>  $payload  A Telegram "ForumTopic" object.
     */
    public static function fromResponse(array $payload): self
    {
        return new self(
            messageThreadId: (int) ($payload['message_thread_id'] ?? 0),
            name: (string) ($payload['name'] ?? ''),
            iconColor: isset($payload['icon_color']) ? (int) $payload['icon_color'] : null,
            iconCustomEmojiId: isset($payload['icon_custom_emoji_id'])
                ? (string) $payload['icon_custom_emoji_id']
                : null,
        );
    }
}
