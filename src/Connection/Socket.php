<?php

namespace CassandraNative\Connection;

use CassandraNative\Cluster\ClusterOptions;
use CassandraNative\Exception\ConnectionException;
use CassandraNative\Exception\TimeoutException;
use CassandraNative\SSL\SSLOptions;

class Socket
{
    protected bool $persistent = false;

    /**
     * @var resource|false
     */
    protected $stream = false;

    /**
     * Connect to a Cassandra host
     *
     * @param string $host
     * @param int $port
     * @param bool $persistent
     * @param float $connectTimeout
     * @throws ConnectionException
     */
    public function connect(
        string $host,
        int $port,
        bool $persistent,
        float $connectTimeout
    ): void {
        $address = 'tcp://' . $host . ':' . $port;
        $connectionFlags = STREAM_CLIENT_CONNECT;

        if ($persistent) {
            $connectionFlags |= STREAM_CLIENT_PERSISTENT;
        }

        $stream = @stream_socket_client(
            $address,
            $errno,
            $errstr,
            $connectTimeout,
            $connectionFlags
        );

        if ($stream === false) {
            throw new ConnectionException('Socket connect to ' . $host . ':' . $port . ' failed: ' . '(' . $errno . ') ' . $errstr);
        }

        $this->stream = $stream;
        $this->persistent = $persistent;
    }

    /**
     * Enables SSL encrypted connections for the socket session
     *
     * @param array $options
     *
     * @throws ConnectionException
     */
    public function enableSSL(array $options): void
    {
        // Persistent connections retain SSL. Check that we already have an SSL enabled connection before trying to
        // enable one
        $meta = stream_get_meta_data($this->stream);
        if (isset($meta['crypto'])) {
            return;
        }

        if (!stream_context_set_option($this->stream, ['ssl' => $options])) {
            fclose($this->stream);
            throw new ConnectionException('Failed to set SSL encryption options');
        }

        if (!stream_socket_enable_crypto($this->stream, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
            fclose($this->stream);
            throw new ConnectionException('Failed to establish an encrypted connection to the Cassandra node');
        }
    }

    /**
     * Sets the read and write timeouts
     *
     * @param float $timeout
     *
     * @throws ConnectionException
     */
    public function setTimeout(float $timeout): void
    {
        if ($timeout <= 0) {
            return;
        }

        $timeoutSeconds = floor($timeout);
        $timeoutMicroseconds = ($timeout - $timeoutSeconds) * 1000000;

        if (!stream_set_timeout($this->stream, $timeoutSeconds, $timeoutMicroseconds)) {
            fclose($this->stream);
            throw new ConnectionException('Failed to set socket timeout');
        }
    }

    /**
     * Checks if a socket is persistent and has already been read from
     *
     * @return bool
     */
    public function isPersistent(): bool
    {
         return $this->persistent && ftell($this->stream) != 0;
    }

    /**
     * Reads data with a specific size from socket.
     *
     * @param int $size Requested data size.
     *
     * @return string Incoming data.
     *
     * @throws ConnectionException
     */
    public function read(int $size): string
    {
        $data = '';
        do {
            $readSize = $size - strlen($data);
            $buff = @fread($this->stream, $readSize);
            if ($buff === false) {
                if (stream_get_meta_data($this->stream)['timed_out']) {
                    throw new TimeoutException('Timeout occurred while reading from socket');
                }

                throw new ConnectionException('Failed to read packet from socket');
            }
            $data .= $buff;
        } while (strlen($data) < $size);

        return $data;
    }

    /**
     * Writes data to the socket
     *
     * @param string $body The body to write
     * @throws ConnectionException
     * @throws TimeoutException
     */
    public function write(string $body): void
    {
        if (fwrite($this->stream, $body) === false) {
            if (stream_get_meta_data($this->stream)['timed_out']) {
                throw new TimeoutException('Timeout occurred while writing to socket');
            }

            throw new ConnectionException('Failed to write packet to socket');
        }
    }

    /**
     * Closes an opened connection.
     */
    public function close(): void
    {
        if (!$this->stream) {
            return;
        }

        fclose($this->stream);
        $this->stream = false;
    }

    function __destruct()
    {
        if ($this->persistent) {
            return;
        }

        $this->close();
    }
}
