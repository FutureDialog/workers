<?php
namespace Futuredialog\PushWorker\Loggers;

class HealthCheckFileLogger
{
    public function healthCheckLog()
    {
        file_put_contents('last_active', time());
        echo 'log' . PHP_EOL;
    }
}