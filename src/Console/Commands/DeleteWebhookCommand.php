<?php

declare(strict_types=1);

namespace Uzhlaravel\TelegramSystem\Console\Commands;

use Illuminate\Console\Command;
use Uzhlaravel\TelegramSystem\Exceptions\TelegramApiException;
use Uzhlaravel\TelegramSystem\Telegram\MultiBotManager;

final class DeleteWebhookCommand extends Command
{
    protected $signature = 'telegramsystem:webhook:delete
        {bot=default : The configured bot name}
        {--drop-pending : Drop all pending updates}';

    protected $description = 'Delete the Telegram webhook for a bot.';

    public function handle(MultiBotManager $bots): int
    {
        /** @var string $bot */
        $bot = $this->argument('bot');

        if (! $bots->has($bot)) {
            $this->components->error("Bot [{$bot}] is not configured.");

            return self::FAILURE;
        }

        try {
            $bots->client($bot)->deleteWebhook((bool) $this->option('drop-pending'));
        } catch (TelegramApiException $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        $this->components->info("Webhook for [{$bot}] deleted.");

        return self::SUCCESS;
    }
}
