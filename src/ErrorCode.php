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
    case ServerError          = 0x0000;
    case ProtocolError        = 0x000A;
    case AuthenticationError  = 0x0100;
    case UnavailableError     = 0x1000;
    case OverloadedError      = 0x1001;
    case IsBootstrappingError = 0x1002;
    case TruncateError        = 0x1003;
    case WriteTimeoutError    = 0x1100;
    case ReadTimeoutError     = 0x1200;
    case ReadFailureError     = 0x1300;
    case FunctionFailureError = 0x1400;
    case WriteFailureError    = 0x1500;
    case SyntaxError          = 0x2000;
    case UnauthorizedError    = 0x2100;
    case InvalidError         = 0x2200;
    case ConfigError          = 0x2300;
    case AlreadyExistsError   = 0x2400;
    case UnpreparedError      = 0x2500;

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
            self::ServerError, self::OverloadedError, self::UnavailableError, self::IsBootstrappingError, self::TruncateError => ServerException::class,
            self::ProtocolError => ProtocolException::class,
            self::AuthenticationError => AuthenticationException::class,
            self::WriteTimeoutError, self::ReadTimeoutError => TimeoutException::class,
            self::ReadFailureError, self::FunctionFailureError, self::WriteFailureError, self::SyntaxError, self::InvalidError, self::ConfigError, self::AlreadyExistsError, self::UnpreparedError => QueryException::class,
            self::UnauthorizedError => UnauthorizedException::class
        };
        
        throw new $exception($errorMessage, $this->value);
    }
}
