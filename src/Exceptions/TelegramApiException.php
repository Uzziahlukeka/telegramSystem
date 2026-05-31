<?php

declare(strict_types=1);

namespace Uzhlaravel\TelegramSystem\Exceptions;

use RuntimeException;
use Throwable;

/**
 * Wraps any transport/Bot API failure so that raw Telegram error payloads
 * never leak into the domain layer.
 */
final class TelegramApiException extends RuntimeException
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        string $message,
        public readonly ?string $method = null,
        public readonly array $context = [],
        int $code = 0,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }

    /**
     * Build an exception from a decoded Telegram API response.
     *
     * @param  array<string, mixed>  $response
     */
    public static function fromResponse(string $method, array $response): self
    {
        $description = is_string($response['description'] ?? null)
            ? $response['description']
            : 'Unknown Telegram API error.';

        $code = is_int($response['error_code'] ?? null) ? $response['error_code'] : 0;

        return new self(
            message: sprintf('Telegram API call [%s] failed: %s', $method, $description),
            method: $method,
            context: $response,
            code: $code,
        );
    }

    /**
     * Build an exception from a transport-level failure (timeout, DNS, etc.).
     */
    public static function transport(string $method, Throwable $previous): self
    {
        return new self(
            message: sprintf('Telegram API call [%s] could not be completed: %s', $method, $previous->getMessage()),
            method: $method,
            previous: $previous,
        );
    }
}
