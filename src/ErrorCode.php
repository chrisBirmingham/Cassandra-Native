<?php

namespace CassandraNative;

use CassandraNative\Exception\AuthenticationException;
use CassandraNative\Exception\CassandraException;
use CassandraNative\Exception\ProtocolException;
use CassandraNative\Exception\QueryException;
use CassandraNative\Exception\ServerException;
use CassandraNative\Exception\TimeoutException;
use CassandraNative\Exception\UnauthorizedException;

enum ErrorCode : int
{
    case SERVER_ERROR           = 0x0000;
    case PROTOCOL_ERROR         = 0x000A;
    case AUTHENTICATION_ERROR   = 0x0100;
    case UNAVAILABLE_ERROR      = 0x1000;
    case OVERLOADED_ERROR       = 0x1001;
    case IS_BOOTSTRAPPING_ERROR = 0x1002;
    case TRUNCATE_ERROR         = 0x1003;
    case WRITE_TIMEOUT_ERROR    = 0x1100;
    case READ_TIMEOUT_ERROR     = 0x1200;
    case READ_FAILURE_ERROR     = 0x1300;
    case FUNCTION_FAILURE_ERROR = 0x1400;
    case WRITE_FAILURE_ERROR    = 0x1500;
    case SYNTAX_ERROR           = 0x2000;
    case UNAUTHORIZED_ERROR     = 0x2100;
    case INVALID_ERROR          = 0x2200;
    case CONFIG_ERROR           = 0x2300;
    case ALREADY_EXISTS_ERROR   = 0x2400;
    case UNPREPARED_ERROR       = 0x2500;

    /**
     * Converts an error message returned from Cassandra into an exception
     *
     * @param string $errorMessage The error message returned from cassandra
     *
     * @throws CassandraException
     */
    public function toException(string $errorMessage): never
    {
        $exception = match ($this) {
            self::SERVER_ERROR, self::OVERLOADED_ERROR, self::UNAVAILABLE_ERROR, self::IS_BOOTSTRAPPING_ERROR, self::TRUNCATE_ERROR => ServerException::class,
            self::PROTOCOL_ERROR => ProtocolException::class,
            self::AUTHENTICATION_ERROR => AuthenticationException::class,
            self::WRITE_TIMEOUT_ERROR, self::READ_TIMEOUT_ERROR => TimeoutException::class,
            self::READ_FAILURE_ERROR, self::FUNCTION_FAILURE_ERROR, self::WRITE_FAILURE_ERROR, self::SYNTAX_ERROR, self::INVALID_ERROR, self::CONFIG_ERROR, self::ALREADY_EXISTS_ERROR, self::UNPREPARED_ERROR => QueryException::class,
            self::UNAUTHORIZED_ERROR => UnauthorizedException::class
        };
        
        throw new $exception($errorMessage, $this->value);
    }
}
