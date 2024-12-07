<?php

namespace CassandraNative\Type;

use Exception;

class TupleFactory
{
	protected array $types;

	/**
	 * @param array $types
	 *
	 * @throws \InvalidArgumentException
	 */
	public function __construct(array $types)
	{
		if (empty($types)) {
			throw new \InvalidArgumentException('Types cannot be empty');
		}

		$this->types = $types;
	}

	/**
	 * @param array $values List of values for the tuple.
	 *
	 * @return Tuple
	 *
	 * @throws \InvalidArgumentException
	 */
	public function create(array $values): Tuple
	{
		$typeCount = count($this->types);
		$valuesCount = count($values);

		if ($valuesCount !== $typeCount) {
			throw new \InvalidArgumentException("Number of provided tuple values does not match expected number. Expected $typeCount got $valuesCount");
		}

		return new Tuple($this->types, $values);
	}
}
