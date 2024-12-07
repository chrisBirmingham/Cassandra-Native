<?php

namespace CassandraNative\Type;

class MapType implements \ArrayAccess, \Countable, \Iterator
{
	protected int $keyType;

	protected int $valueType;

	protected array $values;

	/**
	 * @param int $keyType
	 * @param int $valueType
	 * @param array $values
	 */
	public function __construct(
		int $keyType,
		int $valueType,
		array $values
	) {
		$this->keyType = $keyType;
		$this->valueType = $valueType;
		$this->values = $values;
	}

	/**
	 * @return int
	 */
	public function getKeyType(): int
	{
		return $this->keyType;
	}

	/**
	 * @return int
	 */
	public function getValueType(): int
	{
		return $this->valueType;
	}

	/**
	 * {@inheritdoc}
	 */
	public function count(): int
	{
		return count($this->values);
	}

	/**
     * {@inheritDoc}
     */
    public function rewind(): void
    {
        reset($this->values);
    }

    /**
     * {@inheritDoc}
     */
    public function current(): mixed
    {
        return current($this->values);
    }

    /**
     * {@inheritDoc}
     */
    public function key(): mixed
    {
        return key($this->values);
    }

    /**
     * {@inheritDoc}
     */
    public function next(): void
    {
        next($this->values);
    }

    /**
     * {@inheritDoc}
     */
    public function valid(): bool
    {
        return key($this->values) !== null;
    }

    /**
     * {@inheritDoc}
     */
    public function offsetExists(mixed $offset): bool
    {
        return isset($this->values[$offset]);
    }

    /**
     * {@inheritDoc}
     */
    public function offsetGet(mixed $offset): mixed
    {
        return $this->values[$offset] ?? null;
    }

    /**
     * {@inheritDoc}
     */
    public function offsetSet(mixed $offset, mixed $value): void
    {
        $this->values[$offset] = $value;
    }

    /**
     * {@inheritDoc}
     */
    public function offsetUnset(mixed $offset): void
    {
        unset($this->values[$offset]);
    }
}
