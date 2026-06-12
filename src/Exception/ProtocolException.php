<?php

namespace CassandraNative\Exception;

use CassandraNative\Opcode;

/**
 * Exception thrown when there is an invalid response from cassandra
 */
class ProtocolException extends CassandraException
{
    /**
     * @param string $message
     * @param Opcode|int $code
     */
    public function __construct(string $message, Opcode|int $code = 0)
    {
        if ($code instanceof Opcode) {
            $code = $code->value;
        }

        parent::__construct($message, $code);
    }
}
