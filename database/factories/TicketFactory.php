<?php

declare(strict_types=1);

namespace Uzhlaravel\TelegramSystem\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Uzhlaravel\TelegramSystem\Tickets\Ticket;
use Uzhlaravel\TelegramSystem\Tickets\TicketStatus;

/**
 * @extends Factory<Ticket>
 */
final class TicketFactory extends Factory
{
    protected $model = Ticket::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'bot' => 'default',
            'chat_id' => (string) $this->faker->numberBetween(-1_000_000_000, -1),
            'message_thread_id' => null,
            'owner_id' => $this->faker->unique()->numberBetween(1_000, 9_999_999),
            'owner_username' => $this->faker->userName(),
            'agent_id' => null,
            'agent_username' => null,
            'subject' => $this->faker->sentence(3),
            'status' => TicketStatus::Open,
            'last_message_at' => null,
        ];
    }

    public function withTopic(int $threadId = 42): self
    {
        return $this->state(fn (): array => ['message_thread_id' => $threadId]);
    }

    public function assigned(int $agentId = 555, ?string $username = 'agent'): self
    {
        return $this->state(fn (): array => [
            'agent_id' => $agentId,
            'agent_username' => $username,
            'status' => TicketStatus::Assigned,
        ]);
    }

    public function closed(): self
    {
        return $this->state(fn (): array => ['status' => TicketStatus::Closed]);
    }
}
