<?php

declare(strict_types=1);

namespace Uzhlaravel\TelegramSystem;

use Illuminate\Support\Facades\Event;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Uzhlaravel\TelegramSystem\Console\Commands\DeleteWebhookCommand;
use Uzhlaravel\TelegramSystem\Console\Commands\PollUpdatesCommand;
use Uzhlaravel\TelegramSystem\Console\Commands\SetWebhookCommand;
use Uzhlaravel\TelegramSystem\Console\Commands\TestCommand;
use Uzhlaravel\TelegramSystem\Contracts\TicketRepositoryInterface;
use Uzhlaravel\TelegramSystem\Events\TicketAssigned;
use Uzhlaravel\TelegramSystem\Events\TicketClosed;
use Uzhlaravel\TelegramSystem\Events\TicketCreated;
use Uzhlaravel\TelegramSystem\Events\TicketReopened;
use Uzhlaravel\TelegramSystem\Listeners\SendTicketAssignedNotification;
use Uzhlaravel\TelegramSystem\Listeners\SendTicketClosedNotification;
use Uzhlaravel\TelegramSystem\Listeners\SendTicketCreatedNotification;
use Uzhlaravel\TelegramSystem\Listeners\SendTicketReopenedNotification;
use Uzhlaravel\TelegramSystem\Repositories\EloquentTicketRepository;
use Uzhlaravel\TelegramSystem\Telegram\MultiBotManager;
use Uzhlaravel\TelegramSystem\Telegram\SupportBridge;
use Uzhlaravel\TelegramSystem\Telegram\TelegramlogsBridge;
use Uzhlaravel\TelegramSystem\Telegram\TopicManager;
use Uzhlaravel\TelegramSystem\Telegram\WebhookHandler;
use Uzhlaravel\TelegramSystem\Tickets\TicketPolicy;
use Uzhlaravel\TelegramSystem\WebChat\WebChatService;

class TelegramSystemServiceProvider extends PackageServiceProvider
{
    /**
     * Map of ticket events to their notification listeners.
     *
     * @var array<class-string, class-string>
     */
    private const LISTENERS = [
        TicketCreated::class => SendTicketCreatedNotification::class,
        TicketAssigned::class => SendTicketAssignedNotification::class,
        TicketClosed::class => SendTicketClosedNotification::class,
        TicketReopened::class => SendTicketReopenedNotification::class,
    ];

    public function configurePackage(Package $package): void
    {
        /*
         * Publishes use the telegramsystem-* tag prefix:
         *   telegramsystem-config, telegramsystem-migrations, telegramsystem-views
         */
        $package
            ->name('telegramsystem')
            ->hasConfigFile()
            ->hasViews()
            ->hasMigration('create_telegramsystem_tickets_table')
            ->hasMigration('create_telegramsystem_ticket_messages_table')
            ->hasRoute('webhook')
            ->hasCommands([
                SetWebhookCommand::class,
                DeleteWebhookCommand::class,
                PollUpdatesCommand::class,
                TestCommand::class,
            ]);
    }

    public function registeringPackage(): void
    {
        // Multi-bot manager + Telegram client strategy (shared singleton cache).
        $this->app->singleton(MultiBotManager::class);
        $this->app->singleton(TopicManager::class);
        $this->app->singleton(SupportBridge::class);
        $this->app->singleton(WebhookHandler::class);
        $this->app->singleton(WebChatService::class);
        $this->app->singleton(TelegramSystem::class);

        // Bridge that re-uses telegramlogs for logging, DM and activity.
        $this->app->singleton(TelegramlogsBridge::class);

        // Repository abstraction: all ticket persistence flows through here.
        $this->app->bind(TicketRepositoryInterface::class, EloquentTicketRepository::class);

        // Authorization layer 2: the policy is configured with the admin IDs.
        $this->app->singleton(TicketPolicy::class, function (): TicketPolicy {
            return new TicketPolicy($this->adminIds());
        });
    }

    public function packageBooted(): void
    {
        foreach (self::LISTENERS as $event => $listener) {
            Event::listen($event, $listener);
        }

        // Make the inbound route file publishable as routes/telegramsystem.php.
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../routes/webhook.php' => base_path('routes/telegramsystem.php'),
            ], 'telegramsystem-routes');
        }
    }

    /**
     * Resolve the configured admin Telegram IDs as integers.
     *
     * @return array<int, int>
     */
    private function adminIds(): array
    {
        /** @var array<int, mixed> $admins */
        $admins = (array) config('telegramsystem.admins', []);

        return array_values(array_map(
            static fn (mixed $id): int => (int) $id,
            $admins,
        ));
    }
}
