<?php

namespace CassandraNative\Exception;

/**
 * Exception thrown when the client is unable to establish a connection to any of the provided hosts
 */
class NoHostsAvailableException extends CassandraException
{
    protected array $hostErrors;

    /**
     * @param string $message
     * @param array $hostErrors
     */
    public function __construct(string $message, array $hostErrors)
    {
        parent::__construct($message);
        $this->hostErrors = $hostErrors;
    }

    /**
     * @return array
     */
    public function getHostErrors(): array
    {
        return $this->hostErrors;
    }
}
