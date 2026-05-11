<?php

namespace CassandraNative\Connection;

use CassandraNative\Exception\ConnectionException;
use CassandraNative\SSL\SSLOptions;

class SocketFactory
{
    public function connect(
        string $host,
        int $port,
        float $connectTimeout,
        float $requestTimeout,
        bool $persistent,
        ?SSLOptions $sslOptions = null
    ): Socket {
        $address = 'tcp://' . $host . ':' . $port;
        $connectionFlags = STREAM_CLIENT_CONNECT;

        if ($this->persistent) {
            $connectionFlags |= STREAM_CLIENT_PERSISTENT;
        }

        $stream = @stream_socket_client(
            $address,
            $errno,
            $errstr,
            $this->connectTimeout,
            $connectionFlags
        );

        if ($stream === false) {
            throw new ConnectionException('Socket connect to ' . $host . ':' . $port . ' failed: ' . '(' . $errno . ') ' . $errstr);
        }

        if ($this->sslOptions instanceof SSLOptions) {
            $this->enableSSL($stream, $this->sslOptions->get());
        }

        if ($this->requestTimeout > 0) {
            $this->setTimeout($stream, $this->requestTimeout);
        }

        return new Socket($stream, $persistent);
    }

    /**
     * Enables SSL encrypted connections for the socket session
     *
     * @param resource $stream
     * @param array $options
     *
     * @throws ConnectionException
     */
    protected function enableSSL($stream, array $options): void
    {
        // Persistent connections retain SSL. Check that we already have an SSL enabled connection before trying to
        // enable one
        $meta = stream_get_meta_data($stream);
        if (isset($meta['crypto'])) {
            return;
        }

        if (!stream_context_set_option($stream, ['ssl' => $options])) {
            fclose($stream);
            throw new ConnectionException('Failed to set SSL encryption options');
        }

        if (!stream_socket_enable_crypto($stream, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
            fclose($stream);
            throw new ConnectionException('Failed to establish an encrypted connection to the Cassandra node');
        }
    }

    /**
     * Sets the read and write timeouts
     *
     * @param resource $stream
     * @param float $timeout
     *
     * @throws ConnectionException
     */
    protected function setTimeout($stream, float $timeout): void
    {
        $timeoutSeconds = floor($timeout);
        $timeoutMicroseconds = ($timeout - $timeoutSeconds) * 1000000;

        if (!stream_set_timeout($stream, $timeoutSeconds, $timeoutMicroseconds)) {
            fclose($stream);
            throw new ConnectionException('Failed to set socket timeout');
        }
    }
}
