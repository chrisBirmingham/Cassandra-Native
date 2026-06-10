<?php

namespace CassandraNative\Exception;

use CassandraNative\Opcode;


/**
 * Exception thrown when there is an invalid response from cassandra
 */
class ProtocolException extends CassandraException
{
    public function __construct(string $message, Opcode|int $code = 0)
    {
        if (!is_int($code)) {
            $code = $code->value;
        }

        parent::__construct($message, $code);
    }
}
