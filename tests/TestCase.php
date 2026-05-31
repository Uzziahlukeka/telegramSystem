<?php

declare(strict_types=1);

namespace Uzhlaravel\TelegramSystem\Tests;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Http;
use Orchestra\Testbench\TestCase as Orchestra;
use Uzhlaravel\Telegramlogs\TelegramlogsServiceProvider;
use Uzhlaravel\TelegramSystem\TelegramSystemServiceProvider;
use Uzhlaravel\TelegramSystem\Tickets\TicketPolicy;

class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        Factory::guessFactoryNamesUsing(
            fn (string $modelName) => 'Uzhlaravel\\TelegramSystem\\Database\\Factories\\'.class_basename($modelName).'Factory'
        );

        $this->configureBots();
        $this->runPackageMigrations();

        // Safety net: fail loudly instead of hitting the network if a test makes
        // an unfaked Bot API call. Tests that need HTTP set their own fakes.
        Http::preventStrayRequests();
    }

    protected function getPackageProviders($app)
    {
        return [
            TelegramlogsServiceProvider::class,
            TelegramSystemServiceProvider::class,
        ];
    }

    public function getEnvironmentSetUp($app)
    {
        config()->set('database.default', 'testing');
        config()->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
    }

    /**
     * Set the configured admin Telegram IDs and rebuild the policy singleton.
     *
     * @param  array<int, int>  $ids
     */
    protected function setAdmins(array $ids): void
    {
        config()->set('telegramsystem.admins', $ids);
        $this->app->forgetInstance(TicketPolicy::class);
    }

    private function configureBots(): void
    {
        // telegramlogs requires non-null string credentials when instantiated.
        config()->set('telegramlogs.bot_token', 'TEST-DEFAULT-TOKEN');
        config()->set('telegramlogs.chat_id', '-1001');

        config()->set('telegramsystem.default_bot', 'default');
        config()->set('telegramsystem.use_telegramlogs_for_default', true);
        config()->set('telegramsystem.topics.enabled', false);
        config()->set('telegramsystem.admins', []);
        config()->set('telegramsystem.bots', [
            'default' => [
                'token' => 'DEFAULT-TOKEN',
                'chat_id' => '-1001',
                'topic_id' => null,
                'webhook_secret' => 'default-secret',
                'label' => 'Default',
            ],
            'support' => [
                'token' => 'SUPPORT-TOKEN',
                'chat_id' => '-2002',
                'topic_id' => null,
                'webhook_secret' => 'support-secret',
                'label' => 'Support',
            ],
        ]);

        $this->app->forgetInstance(TicketPolicy::class);
    }

    private function runPackageMigrations(): void
    {
        $migration = include __DIR__.'/../database/migrations/create_telegramsystem_tickets_table.php.stub';
        $migration->up();
    }
}
