<?php

declare(strict_types=1);

namespace Uzhlaravel\TelegramSystem\Telegram;

use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\PendingRequest;
use Throwable;
use Uzhlaravel\TelegramSystem\DTOs\ForumTopicData;
use Uzhlaravel\TelegramSystem\DTOs\UpdateData;
use Uzhlaravel\TelegramSystem\Exceptions\TelegramApiException;

/**
 * A thin, strongly-typed Bot API client built on Laravel's HTTP client.
 *
 * This client implements the inbound + topic + multi-bot surface that
 * uzhlaravel/telegramlogs deliberately does not expose. It never returns raw
 * Telegram error payloads: every failure becomes a TelegramApiException.
 */
final class Client
{
    public function __construct(
        private readonly HttpFactory $http,
        private readonly string $token,
        private readonly string $apiBase = 'https://api.telegram.org',
        private readonly int $timeout = 15,
    ) {}

    /**
     * Send a text message, optionally into a forum topic.
     *
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    public function sendMessage(string $chatId, string $text, array $options = []): array
    {
        $payload = array_merge([
            'chat_id' => $chatId,
            'text' => $text,
        ], $options);

        return $this->call('sendMessage', $payload);
    }

    /**
     * Create a forum topic in a forum-enabled supergroup.
     *
     * @param  array<string, mixed>  $options
     */
    public function createForumTopic(string $chatId, string $name, array $options = []): ForumTopicData
    {
        $result = $this->call('createForumTopic', array_merge([
            'chat_id' => $chatId,
            'name' => $name,
        ], $options));

        return ForumTopicData::fromResponse($result);
    }

    public function closeForumTopic(string $chatId, int $messageThreadId): bool
    {
        $this->call('closeForumTopic', [
            'chat_id' => $chatId,
            'message_thread_id' => $messageThreadId,
        ]);

        return true;
    }

    public function reopenForumTopic(string $chatId, int $messageThreadId): bool
    {
        $this->call('reopenForumTopic', [
            'chat_id' => $chatId,
            'message_thread_id' => $messageThreadId,
        ]);

        return true;
    }

    public function deleteMessage(string $chatId, int $messageId): bool
    {
        $this->call('deleteMessage', [
            'chat_id' => $chatId,
            'message_id' => $messageId,
        ]);

        return true;
    }

    public function setWebhook(string $url, ?string $secretToken = null, ?array $allowedUpdates = null): bool
    {
        $payload = ['url' => $url];

        if ($secretToken !== null && $secretToken !== '') {
            $payload['secret_token'] = $secretToken;
        }

        if ($allowedUpdates !== null) {
            $payload['allowed_updates'] = $allowedUpdates;
        }

        $this->call('setWebhook', $payload);

        return true;
    }

    public function deleteWebhook(bool $dropPendingUpdates = false): bool
    {
        $this->call('deleteWebhook', ['drop_pending_updates' => $dropPendingUpdates]);

        return true;
    }

    /**
     * Long-polling fetch. Returns typed updates.
     *
     * @return array<int, UpdateData>
     */
    public function getUpdates(int $offset = 0, int $timeout = 30, int $limit = 100): array
    {
        $result = $this->callRaw('getUpdates', [
            'offset' => $offset,
            'timeout' => $timeout,
            'limit' => $limit,
        ]);

        /** @var array<int, array<string, mixed>> $updates */
        $updates = is_array($result['result'] ?? null) ? $result['result'] : [];

        return array_map(
            static fn (array $update): UpdateData => UpdateData::fromResponse($update),
            $updates,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function getMe(): array
    {
        return $this->call('getMe', []);
    }

    /**
     * Perform a call and return the unwrapped "result" payload.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function call(string $method, array $payload): array
    {
        $response = $this->callRaw($method, $payload);

        /** @var array<string, mixed> $result */
        $result = is_array($response['result'] ?? null) ? $response['result'] : [];

        return $result;
    }

    /**
     * Perform a raw call and return the full decoded response, validating "ok".
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function callRaw(string $method, array $payload): array
    {
        try {
            $response = $this->request()->post($this->endpoint($method), $payload);
        } catch (Throwable $e) {
            throw TelegramApiException::transport($method, $e);
        }

        /** @var array<string, mixed> $decoded */
        $decoded = (array) $response->json();

        if (($decoded['ok'] ?? false) !== true) {
            throw TelegramApiException::fromResponse($method, $decoded);
        }

        return $decoded;
    }

    private function request(): PendingRequest
    {
        return $this->http->timeout($this->timeout)->asJson()->acceptJson();
    }

    private function endpoint(string $method): string
    {
        return sprintf('%s/bot%s/%s', rtrim($this->apiBase, '/'), $this->token, $method);
    }
}
