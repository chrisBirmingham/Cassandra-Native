<?php

namespace CassandraNative\Statement;

readonly class PreparedStatement implements StatementInterface
{
    public function __construct(
        public string $id,
        public array $columns
    ) {}
}
