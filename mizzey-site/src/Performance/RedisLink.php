<?php

/**
 * @package MizzeySite
 */

declare(strict_types=1);

namespace MizzeySite\Performance;

defined('ABSPATH') || exit;

/**
 * The page cache's connection to Redis: a few commands over the Redis protocol (RESP), on a plain socket.
 *
 * The page cache answers before WordPress, and its object cache, have loaded, so it cannot borrow their connection;
 * and it must not depend on an extension, so it works the same wherever PHP runs. It sends GET, MGET, SET, INCR and
 * the connection's AUTH and SELECT, and reads the five reply types. Every failure (no answer within the timeout, a
 * refused connection, a Redis error) is a `RedisFailure`, which the page cache turns into "no cache" for the request.
 */
final class RedisLink
{
    /** @var resource */
    private $socket;

    /** @param resource $socket */
    private function __construct($socket)
    {
        $this->socket = $socket;
    }

    /** A connection, authenticated and on the site's database. */
    public static function open(RedisSettings $settings): self
    {
        $errno  = 0;
        $errstr = '';
        $socket = @stream_socket_client('tcp://' . $settings->host . ':' . $settings->port, $errno, $errstr, min($settings->timeout, RedisSettings::PROBE_SECONDS)); // phpcs:ignore WordPress.PHP.NoSilencedErrors
        if (! is_resource($socket)) {
            throw new RedisFailure('unreachable');
        }
        $seconds = (int) floor($settings->timeout);
        stream_set_timeout($socket, $seconds, (int) round(($settings->timeout - $seconds) * 1000000));
        $link = new self($socket);
        if ($settings->password !== '') {
            $link->command('AUTH', $settings->password);
        }
        if ($settings->database > 0) {
            $link->command('SELECT', (string) $settings->database);
        }

        return $link;
    }

    /** Sends one command and returns its reply: a string, an integer, null, or a list of these. */
    public function command(string ...$parts): mixed
    {
        $out = '*' . count($parts) . "\r\n";
        foreach ($parts as $part) {
            $out .= '$' . strlen($part) . "\r\n" . $part . "\r\n";
        }
        $this->write($out);

        return $this->reply();
    }

    public function close(): void
    {
        if (is_resource($this->socket)) {
            fclose($this->socket); // phpcs:ignore WordPress.WP.AlternativeFunctions
        }
    }

    private function write(string $data): void
    {
        while ($data !== '') {
            $sent = @fwrite($this->socket, $data); // phpcs:ignore WordPress.WP.AlternativeFunctions, WordPress.PHP.NoSilencedErrors
            if ($sent === false || $sent === 0) {
                throw new RedisFailure('not-sent');
            }
            $data = (string) substr($data, $sent);
        }
    }

    private function line(): string
    {
        $line = fgets($this->socket);
        if ($line === false || ! str_ends_with($line, "\r\n")) {
            throw new RedisFailure('no-answer');
        }

        return substr($line, 0, -2);
    }

    private function exactly(int $length): string
    {
        $data = '';
        while (strlen($data) < $length) {
            $chunk = fread($this->socket, $length - strlen($data)); // phpcs:ignore WordPress.WP.AlternativeFunctions
            if ($chunk === false || $chunk === '') {
                throw new RedisFailure('no-answer');
            }
            $data .= $chunk;
        }

        return $data;
    }

    private function reply(): mixed
    {
        $line = $this->line();
        $type = $line[0] ?? '';
        $rest = substr($line, 1);
        switch ($type) {
            case '+':
                return $rest;
            case '-':
                throw new RedisFailure('refused', $rest);
            case ':':
                return (int) $rest;
            case '$':
                $length = (int) $rest;
                if ($length < 0) {
                    return null;
                }
                $data = $this->exactly($length + 2);

                return substr($data, 0, $length);
            case '*':
                $count = (int) $rest;
                if ($count < 0) {
                    return null;
                }
                $items = [];
                for ($i = 0; $i < $count; $i++) {
                    $items[] = $this->reply();
                }

                return $items;
        }
        throw new RedisFailure('unreadable');
    }
}
