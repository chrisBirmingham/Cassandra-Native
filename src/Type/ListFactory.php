<?php

namespace CassandraNative\Type;

use CassandraNative\Cassandra;

class ListFactory
{
	protected int $type;

	/**
	 * @param int $type The type of all the items within the list or Set
	 *
	 * @throws \InvalidArgumentException
	 */
	public function __construct(int $type)
	{
		if ($type < Cassandra::COLUMNTYPE_CUSTOM || $type > Cassandra::COLUMNTYPE_TUPLE) {
			throw new \InvalidArgumentException('List/Set type must be one of Cassandra::COLUMNTYPE_*');
		}

		$this->type = $type;
	}

	/**
	 * @param array $values Sequential list of values to store in the List
	 * 						or Set
	 *
	 * @return ListType
	 */
	public function create(array $values): ListType
	{
		return new ListType($this->type, $values);
	}
}
