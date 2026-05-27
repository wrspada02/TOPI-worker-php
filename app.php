<?php

// Requires the phpredis extension:
// https://github.com/phpredis/phpredis
$timeoutSeconds = 10;

$redis = new Redis();

try {
    $redis->connect('127.0.0.1', 6379);

    echo "Connected to Redis\n";
    echo "Waiting for items on queue 'items'...\n";

    while (true) {
        // BRPOP blocks until:
        // - an item is available
        // - or timeout is reached
        //
        // Returns:
        // [queue_name, item]
        // or null/false on timeout

        $result = $redis->brPop(['items'], $timeoutSeconds);

        if ($result === null || $result === false) {
            echo "[" . date('Y-m-d H:i:s') . "] Timeout after {$timeoutSeconds}s, no items found.\n";
            continue;
        }

        [$queue, $item] = $result;

        echo "[" . date('Y-m-d H:i:s') . "] Popped item from '{$queue}': {$item}\n";

        // Process item here
        // ...
    }

} catch (RedisException $e) {
    echo "Redis error: " . $e->getMessage() . PHP_EOL;
    exit(1);
}