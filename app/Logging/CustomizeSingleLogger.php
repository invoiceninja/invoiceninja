<?php

namespace App\Logging;

class CustomizeSingleLogger
{
    /**
     * Customize the given logger instance.
     *
     * @param \Illuminate\Log\Logger $logger
     *
     * @return void
     */
    public function __invoke($logger)
    {
        $logger->pushHandler(new \Monolog\Handler\StreamHandler(storage_path() . '/logs/laravel-info.log',
            \Monolog\Logger::INFO, false));
        $logger->pushHandler(new \Monolog\Handler\StreamHandler(storage_path() . '/logs/laravel-warning.log',
            \Monolog\Logger::WARNING, false));
        $logger->pushHandler(new \Monolog\Handler\StreamHandler(storage_path() . '/logs/laravel-error.log',
            \Monolog\Logger::ERROR, false));
    }
}
