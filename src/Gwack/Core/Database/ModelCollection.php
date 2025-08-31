<?php

namespace Gwack\Core\Database;

use ArrayIterator;
use Countable;
use IteratorAggregate;

/**
 * Model Collection
 *
 * A collection class for handling multiple model instances
 * with array-like functionality and additional helpers.
 *
 * @package Gwack\Core\Database
 */
class ModelCollection implements IteratorAggregate, Countable
{
    private array $items;

    /**
     * ModelCollection constructor
     *
     * @param array $items
     */
    public function __construct(array $items = [])
    {
        $this->items = $items;
    }

    /**
     * Check if collection is empty
     *
     * @return bool
     */
    public function empty(): bool
    {
        return empty($this->items);
    }

    /**
     * Get count of items
     *
     * @return int
     */
    public function count(): int
    {
        return count($this->items);
    }

    /**
     * Get first item
     *
     * @return mixed|null
     */
    public function first(): mixed
    {
        return reset($this->items) ?: null;
    }

    /**
     * Get last item
     *
     * @return mixed|null
     */
    public function last(): mixed
    {
        return end($this->items) ?: null;
    }

    /**
     * Convert collection to array
     *
     * @return array
     */
    public function toArray(): array
    {
        return array_map(function ($item) {
            return $item instanceof Model ? $item->toArray() : $item;
        }, $this->items);
    }

    /**
     * Get iterator for foreach loops
     *
     * @return ArrayIterator
     */
    public function getIterator(): ArrayIterator
    {
        return new ArrayIterator($this->items);
    }

    /**
     * Filter collection by user ID (example method for Post model)
     *
     * @param mixed $userId
     * @return self
     */
    public function for(mixed $userId): self
    {
        $filtered = array_filter($this->items, function ($item) use ($userId) {
            return $item instanceof Model && $item->getAttribute('user_id') === $userId;
        });

        return new self($filtered);
    }

    /**
     * Get all items as array
     *
     * @return array
     */
    public function all(): array
    {
        return $this->items;
    }
}
