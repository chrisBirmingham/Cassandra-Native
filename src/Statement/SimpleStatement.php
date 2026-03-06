<?php

namespace CassandraNative\Statement;

class SimpleStatement implements StatementInterface
{
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
