<?php

namespace CassandraNative\Statement;

readonly class PreparedStatement implements StatementInterface
{
    /**
     * @param string $id
     * @param array $columns
     */
    public function __construct(
        public string $id,
        public array $columns
    ) {}

    /**
     * {@inheritDoc}
     */
    public function getStatement(): array
    {
        return [
            'id' => $this->id,
            'columns' => $this->columns
        ];
    }
}
