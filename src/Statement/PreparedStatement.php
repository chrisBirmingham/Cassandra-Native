<?php

namespace CassandraNative\Statement;

readonly class PreparedStatement implements StatementInterface
{
    public string $id;

    public array $columns;

    /**
     * @param string $id
     * @param array $columns
     */
    public function __construct(string $id, array $columns)
    {
        $this->id = $id;
        $this->columns = $columns;
    }

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
