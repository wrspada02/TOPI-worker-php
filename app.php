<?php

require __DIR__ . '/vendor/autoload.php';

use Predis\Client;

$queueName = 'items';
$timeoutSeconds = 10;

$host = getenv('REDIS_HOST') ?: 'redis';
$port = (int) (getenv('REDIS_PORT') ?: 6379);

$workerId = gethostname();

function logMsg($workerId, $msg) {
    echo "[" . date('Y-m-d H:i:s') . "][WORKER {$workerId}] {$msg}\n";
}

function connectRedis($host, $port, $workerId) {
    while (true) {
        try {
            $redis = new Client([
                'scheme' => 'tcp',
                'host'   => $host,
                'port'   => $port,
            ]);

            $redis->connect();

            logMsg($workerId, "Connected to Redis at {$host}:{$port}");
            return $redis;

        } catch (Exception $e) {
            logMsg($workerId, "Redis connection failed: {$e->getMessage()} - retrying in 2s");
            sleep(2);
        }
    }
}

$redis = connectRedis($host, $port, $workerId);

logMsg($workerId, "Waiting for items on queue '{$queueName}'...");

while (true) {
    try {
        $result = $redis->brpop([$queueName], $timeoutSeconds);

        if ($result === null) {
            // silent or low-noise log
            continue;
        }

        [$queue, $item] = $result;

        logMsg($workerId, "Processed item={$item} from queue={$queue}");

    } catch (Exception $e) {
        logMsg($workerId, "Redis error: {$e->getMessage()} - reconnecting");

        $redis = connectRedis($host, $port, $workerId);
    }
}
