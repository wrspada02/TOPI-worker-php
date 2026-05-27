<?php

require __DIR__ . '/vendor/autoload.php';

use Predis\Client;

$queueName = 'items';
$timeoutSeconds = 10;

try {
    // Create Predis client
    $redis = new Client([
        'scheme' => 'tcp',
        'host'   => '127.0.0.1',
        'port'   => 6379,
    ]);

    // Test connection
    $redis->connect();

    echo "Connected to Redis using Predis\n";
    echo "Waiting for items on queue '{$queueName}'...\n";

    while (true) {

        // BRPOP blocks until:
        // - an item is available
        // - or timeout occurs
        //
        // Returns:
        // [queue_name, item]
        // or null on timeout

        $result = $redis->brpop([$queueName], $timeoutSeconds);

        if ($result === null) {
            echo "[" . date('Y-m-d H:i:s') . "] Timeout after {$timeoutSeconds}s, no items found.\n";
            continue;
        }

        [$queue, $item] = $result;

        echo "[" . date('Y-m-d H:i:s') . "] Popped item from '{$queue}': {$item}\n";

        // Process item here
        // ...
    }

} catch (\Exception $e) {
    echo "Redis error: " . $e->getMessage() . PHP_EOL;
    exit(1);
}