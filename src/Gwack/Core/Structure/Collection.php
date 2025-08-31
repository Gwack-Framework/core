<?php

namespace Gwack\Core\Structure;

class Collection implements \ArrayAccess, \IteratorAggregate, \Countable
{
    protected array $items = [];

    public function __construct(array $items = [])
    {
        $this->items = $items;
    }

    public function offsetExists($offset): bool
    {
        return isset($this->items[$offset]);
    }

    public function offsetGet($offset): mixed
    {
        return $this->items[$offset] ?? null;
    }

    public function offsetSet($offset, $value): void
    {
        if (is_null($offset)) {
            $this->items[] = $value;
        } else {
            $this->items[$offset] = $value;
        }
    }

    public function offsetUnset($offset): void
    {
        unset($this->items[$offset]);
    }

    public function getIterator(): \Traversable
    {
        return new \ArrayIterator($this->items);
    }

    public function count(): int
    {
        return count($this->items);
    }

    public function all(): array
    {
        return $this->items;
    }

    public function each(callable $callback): void
    {
        foreach ($this->items as $key => $value) {
            $callback($value, $key);
        }
    }

    public function filter(callable $callback): Collection
    {
        return new self(array_filter($this->items, $callback));
    }

    public function map(callable $callback): Collection
    {
        return new self(array_map($callback, $this->items));
    }

    public function first(): mixed
    {
        return reset($this->items) ?: null;
    }

    public function last(): mixed
    {
        return end($this->items) ?: null;
    }

    public function toArray(): array
    {
        return $this->items;
    }

    public function toJson(): string
    {
        return json_encode($this->items);
    }

    public function __get($name)
    {
        return $this->items[$name] ?? null;
    }

    public function __set($name, $value)
    {
        $this->items[$name] = $value;
    }
}