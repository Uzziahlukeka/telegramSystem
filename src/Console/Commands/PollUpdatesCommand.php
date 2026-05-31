<?php

declare(strict_types=1);

namespace Uzhlaravel\TelegramSystem\Console\Commands;

use Illuminate\Console\Command;
use Uzhlaravel\TelegramSystem\Exceptions\TelegramApiException;
use Uzhlaravel\TelegramSystem\Exceptions\UnauthorizedTicketAccessException;
use Uzhlaravel\TelegramSystem\Telegram\MultiBotManager;
use Uzhlaravel\TelegramSystem\Telegram\WebhookHandler;

/**
 * Long-polling daemon for local development (an alternative to webhooks).
 */
final class PollUpdatesCommand extends Command
{
    protected $signature = 'telegramsystem:poll
        {bot=default : The configured bot name}
        {--timeout=30 : Long-poll timeout in seconds}
        {--once : Process a single batch and exit (useful for testing)}';

    protected $description = 'Fetch inbound Telegram updates via long polling (getUpdates).';

    public function handle(MultiBotManager $bots, WebhookHandler $handler): int
    {
        /** @var string $bot */
        $bot = $this->argument('bot');

        if (! $bots->has($bot)) {
            $this->components->error("Bot [{$bot}] is not configured.");

            return self::FAILURE;
        }

        $client = $bots->client($bot);
        $timeout = (int) $this->option('timeout');
        $offset = 0;

        $this->components->info("Polling updates for [{$bot}] (Ctrl+C to stop)…");

        do {
            try {
                $updates = $client->getUpdates($offset, $timeout);
            } catch (TelegramApiException $e) {
                $this->components->error($e->getMessage());

                return self::FAILURE;
            }

            foreach ($updates as $update) {
                $offset = $update->updateId + 1;

                try {
                    $handler->handle($bot, $update);
                } catch (UnauthorizedTicketAccessException $e) {
                    $this->components->warn($e->getMessage());
                }
            }
        } while (! (bool) $this->option('once'));

        return self::SUCCESS;
    }
}
