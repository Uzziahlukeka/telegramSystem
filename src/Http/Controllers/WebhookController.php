<?php

declare(strict_types=1);

namespace Uzhlaravel\TelegramSystem\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Uzhlaravel\TelegramSystem\DTOs\UpdateData;
use Uzhlaravel\TelegramSystem\Exceptions\TelegramApiException;
use Uzhlaravel\TelegramSystem\Exceptions\UnauthorizedTicketAccessException;
use Uzhlaravel\TelegramSystem\Telegram\MultiBotManager;
use Uzhlaravel\TelegramSystem\Telegram\WebhookHandler;

/**
 * Receives inbound Telegram updates for a named bot.
 *
 * The bot is resolved from the route, the X-Telegram-Bot-Api-Secret-Token
 * header is validated against that bot's configured secret, and the update is
 * dispatched to the {@see WebhookHandler}. Domain failures are swallowed (and
 * logged) so Telegram always receives a 2xx and does not retry indefinitely.
 */
final class WebhookController
{
    public function __construct(
        private readonly MultiBotManager $bots,
        private readonly WebhookHandler $handler,
    ) {}

    public function __invoke(Request $request, string $bot): JsonResponse
    {
        if (! $this->bots->has($bot)) {
            return new JsonResponse(['ok' => false, 'error' => 'unknown_bot'], 404);
        }

        if (! $this->secretMatches($request, $bot)) {
            return new JsonResponse(['ok' => false, 'error' => 'invalid_secret'], 403);
        }

        /** @var array<string, mixed> $payload */
        $payload = $request->json()->all();

        try {
            $this->handler->handle($bot, UpdateData::fromResponse($payload));
        } catch (UnauthorizedTicketAccessException $e) {
            Log::info('telegramsystem: rejected unauthorized ticket reply.', [
                'bot' => $bot,
                'message' => $e->getMessage(),
            ]);
        } catch (TelegramApiException $e) {
            Log::warning('telegramsystem: Telegram API failure while handling update.', [
                'bot' => $bot,
                'method' => $e->method,
                'message' => $e->getMessage(),
            ]);
        }

        return new JsonResponse(['ok' => true]);
    }

    private function secretMatches(Request $request, string $bot): bool
    {
        $expected = $this->bots->webhookSecret($bot);

        if ($expected === null || $expected === '') {
            return true;
        }

        $provided = (string) $request->header('X-Telegram-Bot-Api-Secret-Token', '');

        return hash_equals($expected, $provided);
    }
}
