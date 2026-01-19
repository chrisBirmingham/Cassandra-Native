<?php

namespace CassandraNative\Statement;

class SimpleStatement implements StatementInterface
{
    /**
     * @param string $query
     */
    public function __construct(
        protected string $query
    ) {}

    /**
     * {@inheritDoc}
     */
    public function getStatement(): string
    {
        return $this->query;
    }
}
