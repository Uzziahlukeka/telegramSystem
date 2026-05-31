<?php

declare(strict_types=1);

namespace Uzhlaravel\TelegramSystem\Tickets;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Uzhlaravel\TelegramSystem\Database\Factories\TicketFactory;

/**
 * A support ticket: one non-admin contact (owner) talking to, at most, one
 * assigned agent inside a Telegram chat (optionally a forum topic).
 *
 * @property int $id
 * @property string $bot
 * @property string $chat_id
 * @property int|null $message_thread_id
 * @property int $owner_id
 * @property string|null $owner_username
 * @property int|null $agent_id
 * @property string|null $agent_username
 * @property string|null $subject
 * @property TicketStatus $status
 * @property Carbon|null $last_message_at
 */
class Ticket extends Model
{
    /** @use HasFactory<TicketFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'bot',
        'chat_id',
        'message_thread_id',
        'owner_id',
        'owner_username',
        'agent_id',
        'agent_username',
        'subject',
        'status',
        'last_message_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'message_thread_id' => 'integer',
            'owner_id' => 'integer',
            'agent_id' => 'integer',
            'status' => TicketStatus::class,
            'last_message_at' => 'datetime',
        ];
    }

    public function getTable(): string
    {
        return $this->table ?? (string) config('telegramsystem.table', 'telegramsystem_tickets');
    }

    /**
     * Query authorization layer: the tickets a given Telegram user may see.
     * Admins see everything; everyone else only the tickets they own or are
     * the assigned agent on.
     *
     * @return Builder<self>
     */
    public static function visibleTo(int $telegramUserId, bool $isAdmin = false): Builder
    {
        /** @var Builder<self> $query */
        $query = self::query();

        if ($isAdmin) {
            return $query;
        }

        return $query->where(function (Builder $inner) use ($telegramUserId): void {
            $inner->where('owner_id', $telegramUserId)
                ->orWhere('agent_id', $telegramUserId);
        });
    }

    protected static function newFactory(): Factory
    {
        return TicketFactory::new();
    }
}
