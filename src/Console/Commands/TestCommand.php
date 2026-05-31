<?php

declare(strict_types=1);

namespace Uzhlaravel\TelegramSystem\Console\Commands;

use Illuminate\Console\Command;
use Uzhlaravel\TelegramSystem\Exceptions\TelegramApiException;
use Uzhlaravel\TelegramSystem\Telegram\MultiBotManager;

final class TestCommand extends Command
{
    protected $signature = 'telegramsystem:test
        {bot=default : The configured bot name}
        {--chat= : Override the chat ID to send the test message to}';

    protected $description = 'Send a test message through a configured bot to verify connectivity.';

    public function handle(MultiBotManager $bots): int
    {
        /** @var string $bot */
        $bot = $this->argument('bot');

        if (! $bots->has($bot)) {
            $this->components->error("Bot [{$bot}] is not configured.");

            return self::FAILURE;
        }

        $chatId = $this->option('chat');
        $chatId = is_string($chatId) && $chatId !== '' ? $chatId : $bots->chatId($bot);

        if ($chatId === null) {
            $this->components->error("No chat ID configured for [{$bot}]; pass --chat=...");

            return self::FAILURE;
        }

        try {
            $bots->sendMessage($bot, $chatId, '✅ telegramsystem connectivity test for ['.$bot.'].');
        } catch (TelegramApiException $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        $this->components->info("Test message sent through [{$bot}].");

        return self::SUCCESS;
    }
}
