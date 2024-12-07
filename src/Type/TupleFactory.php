<?php

namespace CassandraNative\Type;

use CassandraNative\Cassandra;

class TupleFactory
{
	protected array $types;

	/**
	 * @param array $types List of types for each item inside the Tuple
	 *
	 * @throws \InvalidArgumentException
	 */
	public function __construct(array $types)
	{
		if (empty($types)) {
			throw new \InvalidArgumentException('Types cannot be empty');
		}

		foreach ($types as $type) {
			if ($type < Cassandra::COLUMNTYPE_CUSTOM || $type > Cassandra::COLUMNTYPE_TUPLE) {
				throw new \InvalidArgumentException('Tuple types must be one of Cassandra::COLUMNTYPE_*');
			}
		}

		$this->types = $types;
	}

	/**
	 * @param array $values List of values for the tuple.
	 *
	 * @return TupleType
	 *
	 * @throws \InvalidArgumentException
	 */
	public function create(array $values): TupleType
	{
		$typeCount = count($this->types);
		$valuesCount = count($values);

		if ($valuesCount !== $typeCount) {
			throw new \InvalidArgumentException("Number of provided tuple values does not match expected number. Expected $typeCount got $valuesCount");
		}

		return new TupleType($this->types, $values);
	}
}
