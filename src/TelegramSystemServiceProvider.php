<?php

namespace uzhlaravel\TelegramSystem;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use uzhlaravel\TelegramSystem\Commands\TelegramSystemCommand;

class TelegramSystemServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        /*
         * This class is a Package Service Provider
         *
         * More info: https://github.com/spatie/laravel-package-tools
         */
        $package
            ->name('telegramsystem')
            ->hasConfigFile()
            ->hasViews()
            ->hasMigration('create_telegramsystem_table')
            ->hasCommand(TelegramSystemCommand::class);
    }
}
