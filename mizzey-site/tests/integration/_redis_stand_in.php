<?php
/**
 * A stand-in for Redis on a developer's machine, for t121 (the page cache), where no Redis server is installed.
 *
 *     php mizzey-site/tests/integration/_redis_stand_in.php <port> [password]
 *
 * It speaks the Redis protocol (RESP) on 127.0.0.1 and keeps its data in memory, for the commands the page cache and
 * the scenario send: PING, ECHO, AUTH, SELECT, GET, MGET, SET (EX, PX, NX, XX), SETEX, INCR, INCRBY, DEL, EXISTS, TTL,
 * KEYS, DBSIZE, FLUSHDB, INFO, QUIT and CLIENT PAUSE (which, as in Redis, stops answering every other client for the
 * time given: t121 uses it to make Redis unresponsive). It is not Redis: the staging server runs the real one, and the
 * scenario runs against either. Never exposed beyond 127.0.0.1.
 *
 * @package MizzeySite\Tests\Integration
 */

declare(strict_types=1);

$port     = (int) ($argv[1] ?? 6390);
$password = (string) ($argv[2] ?? '');
$server   = stream_socket_server('tcp://127.0.0.1:' . $port, $errno, $errstr);
if (! $server) {
    fwrite(STDERR, "stand-in: cannot listen on {$port}: {$errstr}\n");
    exit(1);
}
fwrite(STDOUT, "stand-in: listening on 127.0.0.1:{$port}\n");

/** @var array<int,array{data:array<string,string>,expires:array<string,float>}> $dbs */
$dbs     = [];
$clients = [];
$paused  = 0.0;

function stand_in_db(array &$dbs, int $index): array
{
    $dbs[$index] ??= ['data' => [], 'expires' => []];
    $now = microtime(true);
    foreach ($dbs[$index]['expires'] as $key => $at) {
        if ($at <= $now) {
            unset($dbs[$index]['data'][$key], $dbs[$index]['expires'][$key]);
        }
    }

    return $dbs[$index];
}

function stand_in_bulk(?string $value): string
{
    return $value === null ? "\$-1\r\n" : '$' . strlen($value) . "\r\n" . $value . "\r\n";
}

/**
 * Reads one command from a client's buffer, or null when it is not complete yet.
 *
 * @return list<string>|null
 */
function stand_in_parse(string &$buffer): ?array
{
    if ($buffer === '' || $buffer[0] !== '*') {
        $end = strpos($buffer, "\r\n");
        if ($end === false) {
            return null;
        }
        $line   = substr($buffer, 0, $end);
        $buffer = (string) substr($buffer, $end + 2);

        return array_values(array_filter(explode(' ', trim($line)), 'strlen'));
    }
    $end = strpos($buffer, "\r\n");
    if ($end === false) {
        return null;
    }
    $count = (int) substr($buffer, 1, $end - 1);
    $pos   = $end + 2;
    $parts = [];
    for ($i = 0; $i < $count; $i++) {
        $end = strpos($buffer, "\r\n", $pos);
        if ($end === false || $buffer[$pos] !== '$') {
            return null;
        }
        $length = (int) substr($buffer, $pos + 1, $end - $pos - 1);
        $start  = $end + 2;
        if (strlen($buffer) < $start + $length + 2) {
            return null;
        }
        $parts[] = substr($buffer, $start, $length);
        $pos     = $start + $length + 2;
    }
    $buffer = (string) substr($buffer, $pos);

    return $parts;
}

/** @param list<string> $cmd */
function stand_in_run(array $cmd, array &$client, array &$dbs, float &$paused, string $password): string
{
    $name = strtoupper($cmd[0] ?? '');
    $args = array_slice($cmd, 1);
    if ($password !== '' && ! $client['auth'] && ! in_array($name, ['AUTH', 'PING', 'QUIT'], true)) {
        return "-NOAUTH Authentication required.\r\n";
    }
    $db = $client['db'];
    stand_in_db($dbs, $db);
    $data    = &$dbs[$db]['data'];
    $expires = &$dbs[$db]['expires'];
    switch ($name) {
        case 'PING':
            return "+PONG\r\n";
        case 'ECHO':
            return stand_in_bulk($args[0] ?? '');
        case 'QUIT':
            $client['quit'] = true;

            return "+OK\r\n";
        case 'AUTH':
            $given = (string) end($args);
            if ($password === '' || hash_equals($password, $given)) {
                $client['auth'] = true;

                return "+OK\r\n";
            }

            return "-WRONGPASS invalid username-password pair\r\n";
        case 'SELECT':
            $client['db'] = (int) ($args[0] ?? 0);

            return "+OK\r\n";
        case 'GET':
            return stand_in_bulk($data[$args[0] ?? ''] ?? null);
        case 'MGET':
            $out = '*' . count($args) . "\r\n";
            foreach ($args as $key) {
                $out .= stand_in_bulk($data[$key] ?? null);
            }

            return $out;
        case 'SETEX':
            $data[$args[0]]    = (string) $args[2];
            $expires[$args[0]] = microtime(true) + (int) $args[1];

            return "+OK\r\n";
        case 'SET':
            [$key, $value] = [$args[0] ?? '', $args[1] ?? ''];
            $ttl  = null;
            $mode = '';
            for ($i = 2; $i < count($args); $i++) {
                $opt = strtoupper($args[$i]);
                if ($opt === 'EX') {
                    $ttl = (float) $args[++$i];
                } elseif ($opt === 'PX') {
                    $ttl = ((float) $args[++$i]) / 1000;
                } elseif ($opt === 'NX' || $opt === 'XX') {
                    $mode = $opt;
                }
            }
            $exists = array_key_exists($key, $data);
            if (($mode === 'NX' && $exists) || ($mode === 'XX' && ! $exists)) {
                return "\$-1\r\n";
            }
            $data[$key] = $value;
            unset($expires[$key]);
            if ($ttl !== null) {
                $expires[$key] = microtime(true) + $ttl;
            }

            return "+OK\r\n";
        case 'INCR':
        case 'INCRBY':
            $key        = $args[0] ?? '';
            $data[$key] = (string) ((int) ($data[$key] ?? 0) + ($name === 'INCR' ? 1 : (int) ($args[1] ?? 1)));

            return ':' . $data[$key] . "\r\n";
        case 'DEL':
            $n = 0;
            foreach ($args as $key) {
                if (array_key_exists($key, $data)) {
                    unset($data[$key], $expires[$key]);
                    $n++;
                }
            }

            return ":{$n}\r\n";
        case 'EXISTS':
            return ':' . count(array_filter($args, static fn ($k) => array_key_exists($k, $data))) . "\r\n";
        case 'TTL':
            $key = $args[0] ?? '';
            if (! array_key_exists($key, $data)) {
                return ":-2\r\n";
            }

            return ':' . (isset($expires[$key]) ? (int) ceil($expires[$key] - microtime(true)) : -1) . "\r\n";
        case 'KEYS':
            $re   = '/^' . str_replace(['\*', '\?'], ['.*', '.'], preg_quote($args[0] ?? '*', '/')) . '$/s';
            $keys = array_values(array_filter(array_keys($data), static fn ($k) => preg_match($re, (string) $k) === 1));
            $out  = '*' . count($keys) . "\r\n";
            foreach ($keys as $key) {
                $out .= stand_in_bulk((string) $key);
            }

            return $out;
        case 'DBSIZE':
            return ':' . count($data) . "\r\n";
        case 'FLUSHDB':
            $data    = [];
            $expires = [];

            return "+OK\r\n";
        case 'INFO':
            return stand_in_bulk("# Server\r\nredis_version:0.0.0-stand-in\r\n");
        case 'CLIENT':
            if (strtoupper($args[0] ?? '') === 'PAUSE') {
                $paused = microtime(true) + ((int) ($args[1] ?? 0)) / 1000;

                return "+OK\r\n";
            }

            return "+OK\r\n";
    }

    return "-ERR unknown command '{$name}'\r\n";
}

while (true) {
    $read = [$server];
    foreach ($clients as $client) {
        $read[] = $client['socket'];
    }
    $write  = null;
    $except = null;
    $wait   = $paused > microtime(true) ? 0 : 1;
    if (@stream_select($read, $write, $except, $wait, $paused > microtime(true) ? 50000 : 0) === false) {
        continue;
    }
    foreach ($read as $socket) {
        if ($socket === $server) {
            $new = @stream_socket_accept($server, 0);
            if ($new) {
                stream_set_blocking($new, false);
                $clients[(int) $new] = ['socket' => $new, 'buffer' => '', 'db' => 0, 'auth' => false, 'quit' => false];
            }
            continue;
        }
        $id    = (int) $socket;
        $chunk = fread($socket, 1048576);
        if ($chunk === '' || $chunk === false) {
            if (feof($socket)) {
                fclose($socket);
                unset($clients[$id]);
            }
            continue;
        }
        $clients[$id]['buffer'] .= $chunk;
    }
    // Answer every complete command, unless paused; the client that paused got its answer before the pause began.
    foreach ($clients as $id => &$client) {
        while ($paused <= microtime(true) && ($cmd = stand_in_parse($client['buffer'])) !== null) {
            $reply = stand_in_run($cmd, $client, $dbs, $paused, $password);
            stream_set_blocking($client['socket'], true);
            fwrite($client['socket'], $reply);
            stream_set_blocking($client['socket'], false);
            if ($client['quit']) {
                break;
            }
        }
        if ($client['quit']) {
            fclose($client['socket']);
            unset($clients[$id]);
        }
    }
    unset($client);
}
