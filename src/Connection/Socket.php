<?php

namespace CassandraNative\Connection;

use CassandraNative\Cluster\ClusterOptions;
use CassandraNative\Exception\ConnectionException;
use CassandraNative\Exception\TimeoutException;
use CassandraNative\SSL\SSLOptions;

class Socket
{
    protected bool $persistent;

    /**
     * @var resource|false
     */
    protected $stream = false;

    /**
     * @param ClusterOptions $clusterOptions
     * @throws ConnectionException
     */
    public function connect(ClusterOptions $clusterOptions): void
    {
        $this->persistent = $clusterOptions->getPersistentSessions();
        $host = $clusterOptions->getHost();
        $port = $clusterOptions->getPort();
        $connectTimeout = $clusterOptions->getConnectTimeout();
        $address = 'tcp://' . $host . ':' . $port;

        $connectionFlags = STREAM_CLIENT_CONNECT;

        if ($this->persistent) {
            $connectionFlags |= STREAM_CLIENT_PERSISTENT;
        }

        $options = [];
        $sslOptions = $clusterOptions->getSSL();

        if ($sslOptions instanceof SSLOptions) {
            $options['ssl'] = $sslOptions->get();
        }

        $stream = @stream_socket_client(
            $address,
            $errno,
            $errstr,
            $connectTimeout,
            $connectionFlags,
            stream_context_create($options)
        );

        if ($stream === false) {
            throw new ConnectionException('Socket connect to ' . $host . ':' . $port . ' failed: ' . '(' . $errno . ') ' . $errstr);
        }

        if ($sslOptions instanceof SSLOptions) {
            if (!stream_socket_enable_crypto($stream, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                throw new ConnectionException('Failed to establish encrypted connection with host ' . $host);
            }
        }

        stream_set_timeout($stream, $clusterOptions->getRequestTimeout());

        $this->stream = $stream;
    }

    /**
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
     * @return string Incoming data, false on error.
     *
     * @throws ConnectionException
     */
    public function read(int $size): string
    {
        $data = '';
        while (strlen($data) < $size) {
            $readSize = $size - strlen($data);
            $buff = @fread($this->stream, $readSize);
            if ($buff === false) {
                if (stream_get_meta_data($this->stream)['timed_out']) {
                    throw new TimeoutException('Timeout occurred while reading from socket');
                }

                throw new ConnectionException('Failed to read packet from socket');
            }
            $data .= $buff;
        }
        return $data;
    }

    /**
     * @param string $body
     * @throws ConnectionException
     * @throws TimeoutException
     */
    public function writeFrame(string $body): void
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