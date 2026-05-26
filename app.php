<?php
// consume_redis_queue.php
// Consome uma fila Redis chamada "items" e processa cada item.
// Uso: php consume_redis_queue.php [--once]
// Dependências: extensão phpredis (preferível) ou biblioteca Predis via Composer.

ini_set('display_errors', '1');
error_reporting(E_ALL);

$config = [
    'host' => getenv('REDIS_HOST') ?: '127.0.0.1',
    'port' => (int)(getenv('REDIS_PORT') ?: 6379),
    'auth' => getenv('REDIS_AUTH') ?: null,
    'db'   => getenv('REDIS_DB') !== false ? (getenv('REDIS_DB') !== '' ? (int)getenv('REDIS_DB') : null) : null,
    'queue'=> getenv('QUEUE_NAME') ?: 'items',
    'timeout' => getenv('BRPOP_TIMEOUT') !== false ? (int)(getenv('BRPOP_TIMEOUT') ?: 0) : 0,
];

$opts = array_slice($argv, 1);
$runOnce = in_array('--once', $opts, true);

$running = true;

if (function_exists('pcntl_signal')) {
    pcntl_async_signals(true);
    pcntl_signal(SIGINT, function() use (&$running) { $running = false; });
    pcntl_signal(SIGTERM, function() use (&$running) { $running = false; });
}

// Conectar ao Redis (phpredis) ou Predis (fallback)
$client = null;

if (class_exists('Redis')) {
    $redis = new Redis();
    try {
        $redis->connect($config['host'], $config['port'], 2.5);
        if ($config['auth']) {
            $redis->auth($config['auth']);
        }
        if ($config['db'] !== null) {
            $redis->select($config['db']);
        }
        $client = $redis;
        fwrite(STDOUT, "Conectado ao Redis (phpredis) {$config['host']}:{$config['port']}\n");
    } catch (Exception $e) {
        fwrite(STDERR, "Erro conectando com phpredis: {$e->getMessage()}\n");
        $client = null;
    }
}

if ($client === null && file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
    if (class_exists('Predis\\Client')) {
        try {
            $predis = new Predis\Client([
                'host' => $config['host'],
                'port' => $config['port'],
                'password' => $config['auth'],
                'database' => $config['db'],
            ]);
            $client = $predis;
            fwrite(STDOUT, "Conectado ao Redis (Predis) {$config['host']}:{$config['port']}\n");
        } catch (Exception $e) {
            fwrite(STDERR, "Erro conectando com Predis: {$e->getMessage()}\n");
        }
    }
}

if ($client === null) {
    fwrite(STDERR, "Nenhum cliente Redis disponível. Instale a extensão phpredis ou Predis via Composer.\n");
    exit(1);
}

function log_info($msg) {
    fwrite(STDOUT, date('[Y-m-d H:i:s] ') . $msg . PHP_EOL);
}

function process_item($payload) {
    // Usuário pode customizar o processamento aqui.
    if (is_string($payload)) {
        $decoded = json_decode($payload, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            $data = $decoded;
        } else {
            $data = $payload;
        }
    } else {
        $data = $payload;
    }

    log_info('Processando item: ' . (is_array($data) ? json_encode($data, JSON_UNESCAPED_UNICODE) : (string)$data));

    // Exemplo: simula processamento
    usleep(100000);

    // Se precisar de falha, lance exceção ou retorne false.
    return true;
}

// Loop principal: usa BRPOP para bloqueio (phpredis) ou brpop (Predis)
while ($running) {
    try {
        if (get_class($client) === 'Redis') {
            // phpredis: brPop(array $keys, int $timeout)
            $result = $client->brPop([$config['queue']], $config['timeout']);
            if ($result === null) {
                // timeout sem item
                if ($runOnce) break;
                continue;
            }
            // $result => [queueName, value]
            $value = $result[1];
        } else {
            // Predis
            $res = $client->brpop([$config['queue']], $config['timeout']);
            if ($res === null) {
                if ($runOnce) break;
                continue;
            }
            // Predis retorna array [queueName => value] ou [queueName, value]
            if (is_array($res)) {
                // normaliza
                if (array_values($res) === $res) {
                    $value = $res[1];
                } else {
                    $value = reset($res);
                }
            } else {
                $value = $res;
            }
        }

        if ($value === null) {
            if ($runOnce) break;
            continue;
        }

        process_item($value);

        if ($runOnce) break;
    } catch (Exception $e) {
        fwrite(STDERR, "Erro no loop de consumo: {$e->getMessage()}\n");
        // aguarda um pouco antes de tentar reconectar
        sleep(1);
    }
}

log_info('Saindo do consumidor.');
exit(0);
