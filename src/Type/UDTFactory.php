<?php

namespace CassandraNative\Type;

class UDTFactory
{
    protected string $name;

    protected array $fields;

    /**
     * @param string $name
     * @param array $fields
     */
    public function __construct(string $name, array $fields)
    {
        $this->name = $name;
        $this->fields = $fields;
    }

    /**
     * @param array $values
     * @return UDTType
     */
    public function create(array $values): UDTType
    {
        $values = array_combine(array_keys($this->fields), $values);
        return new UDTType($this->name, $this->fields, $values);
    }
}
