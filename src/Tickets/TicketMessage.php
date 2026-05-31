<?php

declare(strict_types=1);

namespace Uzhlaravel\TelegramSystem\Tickets;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Uzhlaravel\TelegramSystem\Database\Factories\TicketMessageFactory;

/**
 * One line of a ticket's conversation, persisted so it can be replayed in the
 * web-chat widget (Telegram itself keeps no copy the website can read back).
 *
 * The "header" row is bookkeeping: it stores the id of the ticket's anchor
 * message in the Telegram group so inbound agent replies can be threaded back
 * to the originating ticket.
 *
 * @property int $id
 * @property int $ticket_id
 * @property int|null $group_message_id
 * @property string $direction
 * @property string|null $content
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class TicketMessage extends Model
{
    /** @use HasFactory<TicketMessageFactory> */
    use HasFactory;

    /** The ticket's anchor message in the Telegram group (no visible body). */
    public const DIRECTION_HEADER = 'header';

    /** A message the website visitor sent. */
    public const DIRECTION_FROM_USER = 'from_user';

    /** A reply a Telegram agent sent back. */
    public const DIRECTION_FROM_AGENT = 'from_agent';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'ticket_id',
        'group_message_id',
        'direction',
        'content',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'ticket_id' => 'integer',
            'group_message_id' => 'integer',
        ];
    }

    public function getTable(): string
    {
        return $this->table ?? (string) config('telegramsystem.messages_table', 'telegramsystem_ticket_messages');
    }

    /**
     * @return BelongsTo<Ticket, $this>
     */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class, 'ticket_id');
    }

    public function isFromUser(): bool
    {
        return $this->direction === self::DIRECTION_FROM_USER;
    }

    public function isFromAgent(): bool
    {
        return $this->direction === self::DIRECTION_FROM_AGENT;
    }

    protected static function newFactory(): Factory
    {
        return TicketMessageFactory::new();
    }
}
