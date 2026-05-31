<?php

declare(strict_types=1);

namespace Uzhlaravel\TelegramSystem\Tickets;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Uzhlaravel\TelegramSystem\Database\Factories\TicketFactory;

/**
 * A support ticket: one non-admin contact (owner) talking to, at most, one
 * assigned agent inside a Telegram chat (optionally a forum topic).
 *
 * A ticket may originate from Telegram (a contact messaging the bot) or from
 * the web-chat widget (a website visitor); web tickets have no Telegram owner
 * and are correlated to the browser by their web_session_token instead.
 *
 * @property int $id
 * @property string $bot
 * @property string $source
 * @property string|null $ticket_number
 * @property string $chat_id
 * @property int|null $message_thread_id
 * @property int|null $owner_id
 * @property string|null $owner_username
 * @property int|null $agent_id
 * @property string|null $agent_username
 * @property string|null $web_session_token
 * @property string|null $web_email
 * @property string|null $subject
 * @property TicketStatus $status
 * @property Carbon|null $last_message_at
 */
class Ticket extends Model
{
    /** @use HasFactory<TicketFactory> */
    use HasFactory;

    /** A ticket opened by a contact messaging the bot on Telegram. */
    public const SOURCE_TELEGRAM = 'telegram';

    /** A ticket opened from the website's web-chat widget. */
    public const SOURCE_WEB = 'web';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'bot',
        'source',
        'ticket_number',
        'chat_id',
        'message_thread_id',
        'owner_id',
        'owner_username',
        'agent_id',
        'agent_username',
        'web_session_token',
        'web_email',
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

    protected static function booted(): void
    {
        // Stamp a human-friendly reference the first time a ticket is created,
        // unless the caller supplied one explicitly.
        static::creating(function (self $ticket): void {
            if (($ticket->source ?? null) === null) {
                $ticket->source = self::SOURCE_TELEGRAM;
            }

            if (($ticket->ticket_number ?? null) === null) {
                $ticket->ticket_number = self::generateTicketNumber();
            }
        });
    }

    public function getTable(): string
    {
        return $this->table ?? (string) config('telegramsystem.table', 'telegramsystem_tickets');
    }

    /**
     * @return HasMany<TicketMessage, $this>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(TicketMessage::class, 'ticket_id');
    }

    /**
     * The "header" bookkeeping message: the ticket's anchor in the Telegram
     * group that agent replies are threaded under. Null until the ticket has
     * been announced to Telegram.
     */
    public function headerMessage(): ?TicketMessage
    {
        return $this->messages()
            ->where('direction', TicketMessage::DIRECTION_HEADER)
            ->first();
    }

    public function isWeb(): bool
    {
        return $this->source === self::SOURCE_WEB;
    }

    /**
     * Generate a short, unique, human-friendly ticket reference (e.g. T-7F3A9C).
     */
    public static function generateTicketNumber(): string
    {
        do {
            $number = 'T-'.Str::upper(Str::random(6));
        } while (self::query()->where('ticket_number', $number)->exists());

        return $number;
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
