<?php

namespace CassandraNative\Connection;

use CassandraNative\Exception\ConnectionException;
use CassandraNative\Exception\NoHostsAvailableException;
use CassandraNative\SSL\SSLOptions;

class SocketFactory
{
    public function __construct(
        protected int $port,
        protected int $connectTimeout,
        protected int $requestTimeout,
        protected bool $persistent,
        protected ?SSLOptions $sslOptions = null
    ) {}

    /**
     * Attempt to connect to one of the hosts in the cassandra cluster
     *
     * @param string[] $hosts
     * @param int $maxAttempts
     *
     * @throws ConnectException
     * @throws NoHostsAvailableExcepton
     */
    public function connect(array $hosts, int $maxAttempts): Socket
    {
        $connectionErrors = [];
        $attempt = 1;

        do {
            // Choose a random contact host to connect too. If it fails try another one until we either connect to a
            // host or hit max connection attempts
            $index = array_rand($hosts);
            $host = $this->hosts[$index];
            array_splice($this->hosts, $index, 1);

            try {
                $socket = $this->bindSocket($host);
                break;
            } catch (ConnectionException $e) {
                $connectionErrors[$host] = $e->getMessage();

                if ($attempt === $maxAttempts) {
                    throw new NoHostsAvailableException("Failed to connect to a Cassandra Host after $maxAttempts attempt(s)", $connectionErrors);
                }

                $attempt++;
            }
        } while (true);

        if ($this->sslOptions instanceof SSLOptions) {
            $this->enableSSL($socket, $this->sslOptions->get());
        }

        if ($this->requestTimeout > 0) {
            $this->setTimeout($socket);
        }

        return new Socket($socket, $this->persistent);
    }

    /**
     * Create the underlying stream socket
     *
     * @param resource $stream
     *
     * @throws ConnectionException
     */
    protected function bindSocket(string $host): Socket
    {
        $address = "tcp://$host:$this->port";
        $connectionFlags = STREAM_CLIENT_CONNECT;

        if ($this->persistent) {
            $connectionFlags |= STREAM_CLIENT_PERSISTENT;
        }

        $socket = @stream_socket_client(
            $address,
            $errno,
            $errstr,
            $this->connectTimeout,
            $connectionFlags
        );

        if ($socket === false) {
            throw new ConnectionException("Socket connect to $address failed: ($errno) $errstr");
        }

        return socket;
    }

    /**
     * Enables SSL encrypted connections for the socket session
     *
     * @param resource $socket
     * @param array $options
     *
     * @throws ConnectionException
     */
    protected function enableSSL($socket, array $options): void
    {
        // Persistent connections retain SSL. Check that we already have an SSL enabled connection before trying to
        // enable one
        $meta = stream_get_meta_data($socket);
        if (isset($meta['crypto'])) {
            return;
        }

        if (!stream_context_set_option($socket, ['ssl' => $options])) {
            fclose($socket);
            throw new ConnectionException('Failed to set SSL encryption options');
        }

        if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
            fclose($socket);
            throw new ConnectionException('Failed to establish an encrypted connection to the Cassandra node');
        }
    }

    /**
     * Sets the read and write timeouts
     *
     * @param resource $socket
     *
     * @throws ConnectionException
     */
    protected function setTimeout($socket): void
    {
        $timeoutSeconds = floor($this->timeout);
        $timeoutMicroseconds = ($this->timeout - $timeoutSeconds) * 1000000;

        if (!stream_set_timeout($socket, $timeoutSeconds, $timeoutMicroseconds)) {
            fclose($socket);
            throw new ConnectionException('Failed to set socket timeout');
        }
    }
}
