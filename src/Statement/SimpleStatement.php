<?php

namespace CassandraNative\Statement;

readonly class SimpleStatement implements StatementInterface
{
    public function __construct(
        public string $query
    ) {}
}
