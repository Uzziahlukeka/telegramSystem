<?php

declare(strict_types=1);

namespace Uzhlaravel\TelegramSystem\DTOs;

/**
 * A typed view over a Telegram "Update" object. Only the parts relevant to the
 * ticket system are normalised; the original payload is retained for callers
 * that need fields this DTO does not model.
 */
final readonly class UpdateData
{
    /**
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public int $updateId,
        public ?TelegramMessageData $message,
        public array $raw,
    ) {}

    /**
     * @param  array<string, mixed>  $payload  A Telegram "Update" object.
     */
    public static function fromResponse(array $payload): self
    {
        $messagePayload = $payload['message']
            ?? $payload['edited_message']
            ?? $payload['channel_post']
            ?? null;

        return new self(
            updateId: (int) ($payload['update_id'] ?? 0),
            message: is_array($messagePayload) ? TelegramMessageData::fromResponse($messagePayload) : null,
            raw: $payload,
        );
    }

    public function hasMessage(): bool
    {
        return $this->message !== null;
    }
}
