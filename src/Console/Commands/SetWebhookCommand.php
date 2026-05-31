<?php

declare(strict_types=1);

namespace Uzhlaravel\TelegramSystem\Console\Commands;

use Illuminate\Console\Command;
use Uzhlaravel\TelegramSystem\Exceptions\TelegramApiException;
use Uzhlaravel\TelegramSystem\Telegram\MultiBotManager;

final class SetWebhookCommand extends Command
{
    protected $signature = 'telegramsystem:webhook:set
        {bot=default : The configured bot name}
        {--url= : Override the webhook URL (defaults to APP_URL + configured path)}';

    protected $description = 'Register the Telegram webhook for a bot.';

    public function handle(MultiBotManager $bots): int
    {
        /** @var string $bot */
        $bot = $this->argument('bot');

        if (! $bots->has($bot)) {
            $this->components->error("Bot [{$bot}] is not configured.");

            return self::FAILURE;
        }

        $url = $this->resolveUrl($bot);

        try {
            $bots->client($bot)->setWebhook($url, $bots->webhookSecret($bot));
        } catch (TelegramApiException $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        $this->components->info("Webhook for [{$bot}] set to {$url}");

        return self::SUCCESS;
    }

    private function resolveUrl(string $bot): string
    {
        $override = $this->option('url');

        if (is_string($override) && $override !== '') {
            return $override;
        }

        $base = rtrim((string) config('app.url', 'http://localhost'), '/');
        $path = trim((string) config('telegramsystem.webhook.path', 'telegram/webhook'), '/');

        return "{$base}/{$path}/{$bot}";
    }
}
