<?php

namespace uzhlaravel\TelegramSystem\Commands;

use Illuminate\Console\Command;

class TelegramSystemCommand extends Command
{
    public $signature = 'telegramsystem';

    public $description = 'My command';

    public function handle(): int
    {
        $this->comment('All done');

        return self::SUCCESS;
    }
}
