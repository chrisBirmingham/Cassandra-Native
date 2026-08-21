<?php

namespace CassandraNative\Connection;

use CassandraNative\Exception\ConnectionException;
use CassandraNative\Exception\TimeoutException;

class Socket
{
    public function __construct(
        protected $stream,
        protected bool $persistent,
    ) {}

    /**
     * Checks if a socket is persistent and has already been read from
     *
     * @return bool
     */
    public function isPersistent(): bool
    {
         return ftell($this->stream) > 0;
    }

    /**
     * Reads data with a specific size from socket.
     *
     * @param int $length Requested data size.
     *
     * @return string Incoming data.
     *
     * @throws ConnectionException
     */
    public function read(int $length): string
    {
        $data = '';

        do {
            $chunk = fread($this->stream, $length);

            if ($chunk === false || $chunk === '') {
                if (stream_get_meta_data($this->stream)['timed_out']) {
                    throw new TimeoutException('Timeout occurred while reading from socket');
                }

                throw new ConnectionException('Failed to read packet from socket');
            }

            $data .= $chunk;
        } while (($length -= strlen($chunk)) > 0);

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
        $length = strlen($body);

        do {
            $written = fwrite($this->stream, $body);

            if ($length === $written) {
                return;
            }

            if ($written === false || $written < 1) {
                if (stream_get_meta_data($this->stream)['timed_out']) {
                    throw new TimeoutException('Timeout occurred while writing to socket');
                }

                throw new ConnectionException('Failed to write packet to socket');
            }

            $body = substr($body, $written);
        } while (($length = strlen($body)) > 0);
    }

    /**
     * Closes an opened connection.
     */
    public function close(): void
    {
        if (!$this->stream) {
            fclose($this->stream);
            $this->stream = false;
        }
    }

    public function __destruct()
    {
        if (!$this->persistent) {
            $this->close();
        }
    }
}
