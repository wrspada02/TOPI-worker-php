<?php

require __DIR__ . '/vendor/autoload.php';

use Predis\Client;

$queueName = 'items';
$timeoutSeconds = 10;

try {
    $redis = new Client([
        'scheme' => 'tcp',
        'host'   => '127.0.0.1',
        'port'   => 6379,
    ]);

    $redis->connect();

    echo "Connected to Redis using Predis\n";
    echo "Waiting for items on queue '{$queueName}'...\n";

    while (true) {
        $result = $redis->brpop([$queueName], $timeoutSeconds);

        if ($result === null) {
            echo "[" . date('Y-m-d H:i:s') . "] Timeout after {$timeoutSeconds}s, no items found.\n";
            continue;
        }

        [$queue, $item] = $result;

        echo "[" . date('Y-m-d H:i:s') . "] Popped item from '{$queue}': {$item}\n";
    }

} catch (\Exception $e) {
    echo "Redis error: " . $e->getMessage() . PHP_EOL;
    exit(1);
}