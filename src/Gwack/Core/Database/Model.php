<?php

namespace Gwack\Core\Database;

/**
 * Simple Model Base Class
 *
 * Provides basic ORM-like functionality. This is a placeholder
 * implementation that will be expanded later with a proper ORM.
 *
 * @package Gwack\Core\Database
 */
abstract class Model
{
    protected static string $table = '';
    protected array $attributes = [];
    protected array $fillable = [];

    /**
     * Model constructor
     *
     * @param array $attributes
     */
    public function __construct(array $attributes = [])
    {
        $this->fill($attributes);
    }

    /**
     * Fill model with attributes
     *
     * @param array $attributes
     * @return self
     */
    public function fill(array $attributes): self
    {
        foreach ($attributes as $key => $value) {
            if (empty($this->fillable) || in_array($key, $this->fillable)) {
                $this->attributes[$key] = $value;
            }
        }

        return $this;
    }

    /**
     * Get attribute value
     *
     * @param string $key
     * @return mixed
     */
    public function getAttribute(string $key): mixed
    {
        return $this->attributes[$key] ?? null;
    }

    /**
     * Set attribute value
     *
     * @param string $key
     * @param mixed $value
     * @return void
     */
    public function setAttribute(string $key, mixed $value): void
    {
        $this->attributes[$key] = $value;
    }

    /**
     * Get all attributes
     *
     * @return array
     */
    public function toArray(): array
    {
        return $this->attributes;
    }

    /**
     * Find records by criteria (placeholder implementation)
     *
     * @param string $column
     * @param string $operator
     * @param mixed $value
     * @return ModelCollection
     */
    public static function where(string $column, string $operator, mixed $value): ModelCollection
    {
        // This is a placeholder - in reality this would query a database
        return new ModelCollection([]);
    }

    /**
     * Find all records (placeholder implementation)
     *
     * @return ModelCollection
     */
    public static function all(): ModelCollection
    {
        // This is a placeholder - in reality this would query a database
        return new ModelCollection([]);
    }

    /**
     * Create a new record (placeholder implementation)
     *
     * @param array $attributes
     * @return static
     */
    public static function create(array $attributes): static
    {
        // This is a placeholder - in reality this would insert into database
        return new static($attributes);
    }

    /**
     * Magic getter for attributes
     *
     * @param string $key
     * @return mixed
     */
    public function __get(string $key): mixed
    {
        return $this->getAttribute($key);
    }

    /**
     * Magic setter for attributes
     *
     * @param string $key
     * @param mixed $value
     * @return void
     */
    public function __set(string $key, mixed $value): void
    {
        $this->setAttribute($key, $value);
    }
}
