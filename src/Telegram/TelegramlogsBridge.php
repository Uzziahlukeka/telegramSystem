<?php

declare(strict_types=1);

namespace Uzhlaravel\TelegramSystem\Telegram;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Facades\Log;
use Psr\Log\LoggerInterface;
use Uzhlaravel\Telegramlogs\ActivityLogger;
use Uzhlaravel\Telegramlogs\TelegramMessage;

/**
 * A thin bridge that surfaces uzhlaravel/telegramlogs' three core capabilities —
 * logging, direct messaging and activity notifications — through telegramsystem
 * WITHOUT reimplementing any of them. Every method delegates to the published
 * telegramlogs classes/channel.
 */
final readonly class TelegramlogsBridge
{
    public function __construct(
        private Container $container,
        private Config $config,
    ) {}

    /*
    |--------------------------------------------------------------------------
    | Direct messaging (telegramlogs\TelegramMessage)
    |--------------------------------------------------------------------------
    */

    /**
     * Send a simple text message to the default bot's configured chat.
     *
     * @return array<string, mixed>|bool
     */
    public function message(string $text): array|bool
    {
        return $this->messenger()->message($text);
    }

    /**
     * Send a message with custom Telegram API parameters.
     *
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>|bool
     */
    public function send(string $text, array $options = []): array|bool
    {
        return $this->messenger()->send($text, $options);
    }

    /**
     * Send a direct message to a specific chat.
     *
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>|bool
     */
    public function toChat(string $chatId, string $text, array $options = []): array|bool
    {
        return $this->messenger()->toChat($chatId, $text, $options);
    }

    public function messenger(): TelegramMessage
    {
        return $this->container->make(TelegramMessage::class);
    }

    /*
    |--------------------------------------------------------------------------
    | Activity notifications (telegramlogs\ActivityLogger)
    |--------------------------------------------------------------------------
    */

    /**
     * A fresh, fluent telegramlogs activity logger.
     */
    public function activity(): ActivityLogger
    {
        return $this->container->make(ActivityLogger::class);
    }

    /*
    |--------------------------------------------------------------------------
    | Logging (telegramlogs Monolog channel)
    |--------------------------------------------------------------------------
    */

    /**
     * The telegramlogs-backed log channel.
     */
    public function logger(): LoggerInterface
    {
        return Log::channel($this->channel());
    }

    /**
     * Write a record to the telegramlogs channel at the given level.
     *
     * @param  array<string, mixed>  $context
     */
    public function log(string $level, string $message, array $context = []): void
    {
        Log::channel($this->channel())->log($level, $message, $context);
    }

    private function channel(): string
    {
        return (string) $this->config->get('telegramsystem.log_channel', 'telegram');
    }
}
