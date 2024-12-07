<?php

namespace CassandraNative\Type;

use CassandraNative\Cassandra;

class MapFactory
{
    protected int $keyType;

    protected int $valueType;

    /**
     * @param int $keyType   The type of the maps keys
     * @param int $valueType The type of the maps values
     *
     * @throws \InvalidArgumentException
     */ 
    public function __construct(int $keyType, int $valueType)
    {
        if ($keyType < Cassandra::COLUMNTYPE_CUSTOM || $keyType > Cassandra::COLUMNTYPE_TUPLE) {
            throw new \InvalidArgumentException('Map key type must be one of Cassandra::COLUMNTYPE_*');
        }

        if ($valueType < Cassandra::COLUMNTYPE_CUSTOM || $valueType > Cassandra::COLUMNTYPE_TUPLE) {
            throw new \InvalidArgumentException('Map value type must be one of Cassandra::COLUMNTYPE_*');
        }

        $this->keyType = $keyType;
        $this->valueType = $valueType;
	}

    /**
     * @param array $values Array of key value pairs to insert into the map
     *
     * @return MapType
     */
    public function create(array $values): MapType
    {
        return new MapType($this->keyType, $this->valueType, $values);
    }
}
