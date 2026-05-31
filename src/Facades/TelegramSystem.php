<?php

namespace Uzhlaravel\TelegramSystem\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \uzhlaravel\TelegramSystem\TelegramSystem
 */
class TelegramSystem extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \uzhlaravel\TelegramSystem\TelegramSystem::class;
    }
}
