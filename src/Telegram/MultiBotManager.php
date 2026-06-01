<?php

declare(strict_types=1);

namespace Uzhlaravel\TelegramSystem\Telegram;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Container\Container;
use Illuminate\Http\Client\Factory as HttpFactory;
use InvalidArgumentException;
use Uzhlaravel\Telegramlogs\TelegramMessage;
use Uzhlaravel\TelegramSystem\Tickets\Ticket;

/**
 * Resolves and caches per-bot {@see Client} instances and routes outbound
 * sends to the correct transport:
 *
 *  - The default bot delegates simple text sends to uzhlaravel/telegramlogs
 *    (unless disabled in config).
 *  - Named/extra bots always use this package's HTTP {@see Client}.
 *
 * It is registered as a singleton so the client cache is shared.
 */
final class MultiBotManager
{
    /** @var array<string, Client> */
    private array $clients = [];

    public function __construct(
        private readonly HttpFactory $http,
        private readonly Config $config,
        private readonly Container $container,
    ) {}

    /**
     * The configured default bot name.
     */
    public function defaultBot(): string
    {
        return (string) $this->config->get('telegramsystem.default_bot', 'default');
    }

    public function isDefault(?string $bot): bool
    {
        return ($bot ?? $this->defaultBot()) === $this->defaultBot();
    }

    /**
     * Whether a bot with the given name is configured (and has a token).
     */
    public function has(string $bot): bool
    {
        $config = $this->rawConfig($bot);

        return $config !== null && ! empty($config['token']);
    }

    /**
     * Resolve the raw configuration array for a bot.
     *
     * @return array<string, mixed>
     */
    public function config(?string $bot = null): array
    {
        $bot ??= $this->defaultBot();
        $config = $this->rawConfig($bot);

        if ($config === null) {
            throw new InvalidArgumentException("Telegram bot [{$bot}] is not configured.");
        }

        return $config;
    }

    /**
     * Resolve a typed HTTP client for the given bot.
     */
    public function client(?string $bot = null): Client
    {
        $bot ??= $this->defaultBot();

        return $this->clients[$bot] ??= $this->makeClient($bot);
    }

    /**
     * Resolve the client bound to a ticket's persisted bot.
     */
    public function forTicket(Ticket $ticket): Client
    {
        return $this->client($ticket->bot);
    }

    /**
     * The default chat id configured for a bot (if any).
     */
    public function chatId(?string $bot = null): ?string
    {
        $chatId = $this->config($bot)['chat_id'] ?? null;

        return $chatId === null ? null : (string) $chatId;
    }

    /**
     * The default topic id configured for a bot (if any).
     */
    public function topicId(?string $bot = null): ?int
    {
        $topicId = $this->config($bot)['topic_id'] ?? null;

        return $topicId === null || $topicId === '' ? null : (int) $topicId;
    }

    public function webhookSecret(?string $bot = null): ?string
    {
        $secret = $this->config($bot)['webhook_secret'] ?? null;

        return $secret === null ? null : (string) $secret;
    }

    /**
     * Send a text message through the correct transport for the given bot.
     *
     * For the default bot this delegates to telegramlogs (preserving its
     * environment gating and formatting); named bots use this package's client.
     *
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>|bool
     */
    public function sendMessage(?string $bot, string $chatId, string $text, array $options = []): array|bool
    {
        if ($this->isDefault($bot) && (bool) $this->config->get('telegramsystem.use_telegramlogs_for_default', true)) {
            // Delegate the default bot's simple sends to uzhlaravel/telegramlogs.
            return $this->container->make(TelegramMessage::class)->toChat($chatId, $text, $options);
        }

        return $this->client($bot)->sendMessage($chatId, $text, $options);
    }

    /**
     * Copy a message between chats through a bot's client.
     *
     * Unlike {@see sendMessage()} this never delegates to telegramlogs (which
     * exposes no copy primitive); it always uses this package's HTTP client.
     *
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    public function copyMessage(
        ?string $bot,
        string $toChatId,
        string $fromChatId,
        int $messageId,
        array $options = [],
    ): array {
        return $this->client($bot)->copyMessage($toChatId, $fromChatId, $messageId, $options);
    }

    private function makeClient(string $bot): Client
    {
        $config = $this->config($bot);

        $token = (string) ($config['token'] ?? '');

        if ($token === '') {
            throw new InvalidArgumentException("Telegram bot [{$bot}] has no token configured.");
        }

        return new Client(
            http: $this->http,
            token: $token,
            apiBase: (string) $this->config->get('telegramsystem.api_base', 'https://api.telegram.org'),
            timeout: (int) $this->config->get('telegramsystem.timeout', 15),
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    private function rawConfig(string $bot): ?array
    {
        /** @var array<string, array<string, mixed>> $bots */
        $bots = (array) $this->config->get('telegramsystem.bots', []);

        $config = $bots[$bot] ?? null;

        return is_array($config) ? $config : null;
    }
}
