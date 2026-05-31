<?php

declare(strict_types=1);

namespace Uzhlaravel\TelegramSystem\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Uzhlaravel\TelegramSystem\Tickets\Ticket;
use Uzhlaravel\TelegramSystem\Tickets\TicketMessage;

/**
 * @extends Factory<TicketMessage>
 */
final class TicketMessageFactory extends Factory
{
    protected $model = TicketMessage::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'ticket_id' => Ticket::factory(),
            'group_message_id' => $this->faker->numberBetween(1, 999_999),
            'direction' => TicketMessage::DIRECTION_FROM_USER,
            'content' => $this->faker->sentence(),
        ];
    }

    public function header(): self
    {
        return $this->state(fn (): array => [
            'direction' => TicketMessage::DIRECTION_HEADER,
            'content' => null,
        ]);
    }

    public function fromUser(): self
    {
        return $this->state(fn (): array => [
            'direction' => TicketMessage::DIRECTION_FROM_USER,
        ]);
    }

    public function fromAgent(): self
    {
        return $this->state(fn (): array => [
            'direction' => TicketMessage::DIRECTION_FROM_AGENT,
        ]);
    }
}
