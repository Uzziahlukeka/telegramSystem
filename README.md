# Manange and monitor everything via telegram

[![Latest Version on Packagist](https://img.shields.io/packagist/v/uzhlaravel/telegramsystem.svg?style=flat-square)](https://packagist.org/packages/uzhlaravel/telegramsystem)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/uzhlaravel/telegramsystem/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/uzhlaravel/telegramsystem/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/uzhlaravel/telegramsystem/fix-php-code-style-issues.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/uzhlaravel/telegramsystem/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/uzhlaravel/telegramsystem.svg?style=flat-square)](https://packagist.org/packages/uzhlaravel/telegramsystem)

`uzhlaravel/telegramsystem` turns Telegram into a multi-bot **support-ticket
system** for Laravel. A contact opens a ticket, the **first non-admin agent who
replies is atomically assigned to it**, and from that point on only the owner,
the assigned agent and admins can see or touch the conversation.

It is built on top of [`uzhlaravel/telegramlogs`](https://packagist.org/packages/uzhlaravel/telegramlogs)
and deliberately does **not** reimplement logging, direct messaging or activity
notifications — it reuses `telegramlogs` for those and only adds what
`telegramlogs` does not provide: inbound updates (webhook + long polling),
forum-topic creation, multi-bot routing and the whole ticket domain.

## Table of Contents

- [Features](#features)
- [Requirements](#requirements)
- [Installation](#installation)
- [Configuration](#configuration)
    - [Environment variables](#environment-variables)
    - [Single bot](#single-bot)
    - [Multiple bots](#multiple-bots)
- [Usage](#usage)
    - [Ticket lifecycle](#ticket-lifecycle)
    - [Visibility model](#visibility-model)
    - [Forum topics](#forum-topics)
    - [How notifications flow through telegramlogs](#how-notifications-flow-through-telegramlogs)
- [Webhook setup](#webhook-setup)
    - [Webhook vs long polling](#webhook-vs-long-polling)
- [Artisan command reference](#artisan-command-reference)
- [Architecture overview](#architecture-overview)
- [Testing, Pint & Larastan](#testing-pint--larastan)
- [Security notes](#security-notes)
- [License](#license)

## Features

- 🎫 **Ticket domain** — model, backed-enum status, repository, single-purpose
  actions, events/listeners, policy, exceptions and typed DTOs.
- 🥇 **First-reply assignment** — the first eligible non-admin replier becomes
  the agent, assigned with an **atomic, race-safe** conditional update.
- 🔐 **Three-layer authorization** — query scope → `TicketPolicy` →
  action re-checks, so even a raw inbound webhook cannot bypass access rules.
- 🤖 **Multi-bot support** — configure any number of bots, each routed inbound
  and outbound independently; tickets remember which bot they belong to.
- 📥 **Inbound updates** — webhook controller (with secret-token validation) and
  a `getUpdates` long-polling daemon for local development.
- 🧵 **Forum topics** — one topic per ticket via `createForumTopic`, with optional
  close/reopen sync, and graceful fallback to the main chat.
- 💬 **Web chat widget** — a website-facing Livewire/Volt widget whose visitor
  conversations are mirrored to Telegram and whose agent replies flow back, with
  the full conversation persisted as `TicketMessage` rows.
- ♻️ **Reuses `telegramlogs`** — the default bot's simple outbound sends delegate
  to `telegramlogs`; named bots use this package's typed HTTP client.

## Requirements

- PHP `^8.4`
- Laravel `11`, `12` or `13`
- [`uzhlaravel/telegramlogs`](https://packagist.org/packages/uzhlaravel/telegramlogs) `^1.0.2` (installed automatically)

> **Telegram client strategy.** This package talks to the Bot API for the
> surface `telegramlogs` does not expose (inbound, topics, named bots) using
> **Laravel's HTTP client** — no extra dependencies, no `composer install`
> conflicts. The default bot's simple sends still go through `telegramlogs`.

## Installation

```bash
composer require uzhlaravel/telegramsystem
```

Publish and run the migration:

```bash
php artisan vendor:publish --tag="telegramsystem-migrations"
php artisan migrate
```

Publish the config file:

```bash
php artisan vendor:publish --tag="telegramsystem-config"
```

Optionally publish the views and the inbound route file:

```bash
php artisan vendor:publish --tag="telegramsystem-views"
php artisan vendor:publish --tag="telegramsystem-routes"
```

Because `telegramsystem` depends on `telegramlogs`, you can also run its
installer to scaffold the shared Telegram credentials:

```bash
php artisan telegramlogs:install
```

## Configuration

### Environment variables

| Variable                              | Default                    | Description                                           |
|---------------------------------------|----------------------------|-------------------------------------------------------|
| `TELEGRAM_BOT_TOKEN`                  | –                          | Default bot token (shared with `telegramlogs`).       |
| `TELEGRAM_CHAT_ID`                    | –                          | Default chat/group ID.                                |
| `TELEGRAM_TOPIC_ID`                   | –                          | Default forum topic ID (optional).                    |
| `TELEGRAM_WEBHOOK_SECRET`             | –                          | Secret token validated on the default bot's webhook.  |
| `TELEGRAM_TIMEOUT`                    | `15`                       | HTTP timeout in seconds (shared with `telegramlogs`). |
| `TELEGRAM_API_BASE`                   | `https://api.telegram.org` | Bot API base URL.                                     |
| `TELEGRAM_SYSTEM_DEFAULT_BOT`         | `default`                  | Which configured bot is the default.                  |
| `TELEGRAM_SYSTEM_USE_TELEGRAMLOGS`    | `true`                     | Delegate default-bot sends to `telegramlogs`.         |
| `TELEGRAM_SYSTEM_ADMINS`              | –                          | Comma-separated Telegram user IDs treated as admins.  |
| `TELEGRAM_SYSTEM_VISIBILITY`          | `group_topic`              | `group_topic` or `dm_routed`.                         |
| `TELEGRAM_SYSTEM_TOPICS_ENABLED`      | `true`                     | Create a forum topic per ticket.                      |
| `TELEGRAM_SYSTEM_TOPICS_SYNC`         | `true`                     | Close/reopen the topic with the ticket.               |
| `TELEGRAM_SYSTEM_DELETE_UNAUTHORIZED` | `false`                    | Delete messages from unauthorized users.              |
| `TELEGRAM_SYSTEM_WEBHOOK_ENABLED`     | `true`                     | Register the inbound webhook route.                   |
| `TELEGRAM_SYSTEM_WEBHOOK_PATH`        | `telegram/webhook`         | Base path; the route is `{path}/{bot}`.               |
| `TELEGRAM_SUPPORT_BOT_TOKEN`          | –                          | Token for the example named `support` bot.            |
| `TELEGRAM_SUPPORT_CHAT_ID`            | –                          | Chat ID for the `support` bot.                        |
| `TELEGRAM_SUPPORT_WEBHOOK_SECRET`     | –                          | Secret token for the `support` bot's webhook.         |

### Single bot

Out of the box `telegramsystem` behaves like `telegramlogs`: one bot from the
environment.

```dotenv
TELEGRAM_BOT_TOKEN=123456:abcdef
TELEGRAM_CHAT_ID=-1001234567890
TELEGRAM_WEBHOOK_SECRET=a-long-random-string
TELEGRAM_SYSTEM_ADMINS=11111111,22222222
```

### Multiple bots

Add bots in `config/telegramsystem.php`. Each entry supports `token`, `chat_id`,
`topic_id`, `webhook_secret` and `label`:

```php
'bots' => [
    'default' => [
        'token' => env('TELEGRAM_BOT_TOKEN'),
        'chat_id' => env('TELEGRAM_CHAT_ID'),
        'topic_id' => env('TELEGRAM_TOPIC_ID'),
        'webhook_secret' => env('TELEGRAM_WEBHOOK_SECRET'),
        'label' => 'Default',
    ],

    'support' => [
        'token' => env('TELEGRAM_SUPPORT_BOT_TOKEN'),
        'chat_id' => env('TELEGRAM_SUPPORT_CHAT_ID'),
        'webhook_secret' => env('TELEGRAM_SUPPORT_WEBHOOK_SECRET'),
        'label' => 'Support',
    ],
],
```

Each ticket stores the bot it belongs to, so inbound replies are always resolved
back to the right configuration.

## Usage

The `TelegramSystem` facade is the high-level entry point:

```php
use TelegramSystem;

// Open a ticket for a non-admin contact.
$ticket = TelegramSystem::openTicket(
    bot: 'support',
    chatId: '-1001234567890',
    ownerId: 987654321,
    ownerUsername: 'jane',
    subject: 'Cannot log in',
);

// The first eligible non-admin replier is assigned atomically.
$ticket = TelegramSystem::assignAgent($ticket, agentId: 555000111, agentUsername: 'agent_bob');

// Close / reopen (re-checks authorization, syncs the forum topic).
TelegramSystem::close($ticket, actorId: 555000111);
TelegramSystem::reopen($ticket, actorId: 555000111);
```

In practice you rarely call these by hand — inbound updates drive the whole flow
through the `WebhookHandler`.

### Ticket lifecycle

```
open ──first reply──▶ assigned ──close──▶ closed ──reopen──▶ reopened
```

Statuses are a backed enum (`Uzhlaravel\TelegramSystem\Tickets\TicketStatus`):
`open`, `pending`, `assigned`, `closed`, `reopened`.

Rules:

1. Each ticket has exactly one non-admin **owner**.
2. The first eligible non-admin replier becomes the **assigned agent**.
3. Assignment is **atomic** (`UPDATE … WHERE agent_id IS NULL`) — only the winner
   of a race is assigned, and only the winner fires `TicketAssigned`.
4. After assignment only the owner, the agent and admins may view or reply.
5. Admins override everything and never become the agent themselves.

### Visibility model

Telegram has **no native per-topic privacy** inside a group, so confidentiality
is enforced at the application layer. Pick a model with `TELEGRAM_SYSTEM_VISIBILITY`:

| Model                     | How it works                                                                                                                     | Trade-offs                                                                                                                                                                        |
|---------------------------|----------------------------------------------------------------------------------------------------------------------------------|-----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| `group_topic` *(default)* | The bot **rejects** (and optionally deletes) any message in a ticket topic from someone who is not the owner, agent or an admin. | Simple; everything lives in one group. **Already-posted messages cannot be hidden retroactively** — set `TELEGRAM_SYSTEM_DELETE_UNAUTHORIZED=true` to delete them as they arrive. |
| `dm_routed`               | The real conversation happens in the owner's and agent's **private chats**; the forum topic is an admin-only mirror.             | Strongest privacy, but requires both parties to have started a chat with the bot.                                                                                                 |

Either way, denied access raises `UnauthorizedTicketAccessException`, and the
query scope guarantees a user can never even *enumerate* tickets they are not
part of.

### Forum topics

When `topics.enabled` is true and the target group is a forum, a topic is created
per ticket via `createForumTopic` and its `message_thread_id` is stored. Ticket
messages are routed into that topic, and (with `topics.sync_status`) the topic is
closed/reopened in sync with the ticket. If the group is **not** a forum, the
package degrades gracefully and falls back to the main chat.

### Web chat widget

A website-facing chat widget lets visitors talk to your team without leaving the
site. Each browser conversation becomes a **web ticket** mirrored into a Telegram
group, and replies an agent posts in Telegram flow straight back into the widget.

How it works:

- A visitor's first message opens a web ticket (`source = web`, no Telegram owner;
  the browser is tracked by a session token) and posts a header + the message into
  the configured bot's chat (and topic, if set).
- Every line of the conversation is persisted as a `TicketMessage`
  (`header` / `from_user` / `from_agent`) so the widget can replay it — Telegram
  keeps no copy the website can read back.
- When an agent **replies** to one of the ticket's group messages, the webhook
  links it back to the right ticket and records it as a `from_agent` message, which
  the widget polls for and shows.

Configure which bot the web chat routes through (its `chat_id` is the support
group, its `topic_id` the optional topic):

```php
// config/telegramsystem.php
'web_chat' => [
    'enabled' => env('TELEGRAM_SYSTEM_WEB_CHAT_ENABLED', true),
    'bot'     => env('TELEGRAM_SYSTEM_WEB_CHAT_BOT', 'support'),
],
```

Drive it from your own code through the `WebChatService` (also reachable as
`TelegramSystem::webChat()`):

```php
use TelegramSystem;

// Post a visitor message (opens the ticket on first contact):
$ticket = TelegramSystem::webChat()->send(
    sessionToken: $token,   // a per-browser token you persist in the session
    name: 'Ada Lovelace',
    email: 'ada@example.com',
    message: 'Hi, I need a hand with billing.',
);

// Replay the conversation for the browser:
$lines = TelegramSystem::webChat()->conversation($ticket);
```

A ready-made **Livewire/Volt widget** ships with the package. Publish it and mount
it (or copy it into your Volt component directory):

```bash
php artisan vendor:publish --tag=telegramsystem-views
# resources/views/vendor/telegramsystem/web-chat.blade.php
```

The widget handles the session token, honeypot + rate limiting, validation and
5-second polling for new agent replies; all Telegram I/O is delegated to
`WebChatService`.

### How notifications flow through telegramlogs

Lifecycle events (`TicketCreated`, `TicketAssigned`, `TicketClosed`,
`TicketReopened`) are handled by listeners that send a Telegram message through
the `MultiBotManager`:

- For the **default bot**, the manager delegates to `telegramlogs`
  (`TelegramMessage::toChat`) — preserving its environment gating and formatting.
- For **named bots**, the manager uses this package's typed HTTP client.

You keep `telegramlogs` as the single owner of outbound messaging for the default
bot; `telegramsystem` only adds what it cannot do.

### Logging, direct messaging & activity (reused from telegramlogs)

`telegramsystem` does **not** reimplement logging, direct messaging or activity
notifications — it surfaces `telegramlogs`' own implementations through one
facade so you have a single entry point. All of these delegate to the published
package; nothing is built from scratch:

```php
use TelegramSystem;

// Direct messaging → telegramlogs\TelegramMessage
TelegramSystem::message('Deploy finished ✅');
TelegramSystem::toChat('-1001234567890', 'Hello there', ['parse_mode' => 'Markdown']);

// Activity notifications → telegramlogs\ActivityLogger (fluent)
TelegramSystem::activity()
    ->performedOn($order)
    ->causedBy($user)
    ->event('refunded')
    ->dispatch('Order refunded');

// Logging → the telegramlogs "telegram" Monolog channel
TelegramSystem::log('error', 'Payment gateway timed out', ['order' => $order->id]);
TelegramSystem::logger()->warning('Low stock');
```

The channel used by `log()`/`logger()` is configurable via
`telegramsystem.log_channel` (default `telegram`, the channel `telegramlogs`
registers). Need the lower-level objects directly? `TelegramSystem::telegramlogs()`
returns the bridge.

For per-model activity broadcasting, use `telegramlogs`' trait directly — it is a
transitive dependency and always available:

```php
use Uzhlaravel\Telegramlogs\Traits\HasTelegramActivity;

class Order extends Model
{
    use HasTelegramActivity;
}
```

## Webhook setup

Register the webhook for each bot (uses `APP_URL` + the configured path):

```bash
php artisan telegramsystem:webhook:set default
php artisan telegramsystem:webhook:set support --url=https://example.com/telegram/webhook/support
```

Telegram will then POST updates to `POST {path}/{bot}`. The controller validates
the `X-Telegram-Bot-Api-Secret-Token` header against that bot's `webhook_secret`
before doing anything else.

Remove a webhook with:

```bash
php artisan telegramsystem:webhook:delete support --drop-pending
```

### Webhook vs long polling

- **Webhook** is the default for production — Telegram pushes updates to your app.
- **Long polling** is convenient locally (no public URL needed):

```bash
php artisan telegramsystem:poll support
```

## Artisan command reference

| Command                                                        | Description                                     |
|----------------------------------------------------------------|-------------------------------------------------|
| `telegramsystem:webhook:set {bot=default} {--url=}`            | Register the webhook for a bot.                 |
| `telegramsystem:webhook:delete {bot=default} {--drop-pending}` | Delete the webhook for a bot.                   |
| `telegramsystem:poll {bot=default} {--timeout=30} {--once}`    | Long-poll `getUpdates` and dispatch them.       |
| `telegramsystem:test {bot=default} {--chat=}`                  | Send a connectivity test message through a bot. |

## Architecture overview

```
src/
├── TelegramSystem.php              facade root / coordinator
├── TelegramSystemServiceProvider.php
├── Facades/TelegramSystem.php
├── Contracts/TicketRepositoryInterface.php
├── Repositories/EloquentTicketRepository.php
├── Tickets/{Ticket, TicketStatus, TicketPolicy}.php
├── Actions/{CreateTicket, AssignTicket, CloseTicket}Action.php
├── Events/{TicketCreated, TicketAssigned, TicketClosed, TicketReopened}.php
├── Listeners/SendTicket*Notification.php
├── Exceptions/{TelegramApiException, UnauthorizedTicketAccessException}.php
├── DTOs/{TelegramMessageData, ForumTopicData, UpdateData}.php
├── Telegram/{Client, MultiBotManager, TopicManager, WebhookHandler}.php
├── Http/Controllers/WebhookController.php
└── Console/Commands/{SetWebhook, DeleteWebhook, PollUpdates, Test}Command.php
```

- **Provider bindings** — `MultiBotManager`, `TopicManager`, `WebhookHandler`,
  `TelegramSystem` and `TicketPolicy` are singletons;
  `TicketRepositoryInterface` binds to `EloquentTicketRepository`. The provider
  also loads migrations, registers the webhook route, wires event listeners and
  publishes config/migrations/views/routes under the `telegramsystem-*` tags.
- **Repository** — every ticket read/write goes through
  `TicketRepositoryInterface`; the atomic agent assignment lives here.
- **Actions** — `CreateTicketAction`, `AssignTicketAction`, `CloseTicketAction`
  hold the business logic (and re-check authorization) so controllers and
  listeners stay thin.
- **Events / listeners** — lifecycle events drive `telegramlogs`-backed
  notifications.
- **Exceptions** — `TelegramApiException` wraps every transport/API failure so
  raw Telegram errors never leak into the domain; `UnauthorizedTicketAccessException`
  is thrown consistently across the scope, policy and actions.
- **DTOs** — `TelegramMessageData`, `ForumTopicData`, `UpdateData` are typed,
  readonly objects with `fromResponse()` constructors; raw arrays never travel
  through the domain layer.

## Testing, Pint & Larastan

```bash
composer test       # Pest
composer analyse    # Larastan (level 5)
composer format     # Pint
composer check      # all three
```

## Security notes

- Always set a `webhook_secret` per bot; the controller validates the
  `X-Telegram-Bot-Api-Secret-Token` header with `hash_equals`.
- Authorization is enforced in three independent layers — a compromised or
  malformed inbound update cannot read or mutate tickets it has no rights to.
- Remember the Telegram limitation: in `group_topic` mode, messages that were
  already posted cannot be hidden after the fact. Use `dm_routed` when you need
  the conversation itself to stay private.

## License

The MIT License (MIT). Please see the [License File](LICENSE.md) for more
information.
