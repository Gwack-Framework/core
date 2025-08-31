<?php

namespace Gwack\Core\Session;

/**
 * User Model
 *
 * Simple user representation for session management.
 * This will be replaced by a proper ORM model later.
 *
 * @package Gwack\Core\Session
 */
class User
{
    private array $attributes;

    /**
     * User constructor
     *
     * @param array $attributes User attributes
     */
    public function __construct(array $attributes = [])
    {
        $this->attributes = $attributes;
    }

    /**
     * Create user from array
     *
     * @param array $data User data
     * @return self
     */
    public static function fromArray(array $data): self
    {
        return new self($data);
    }

    /**
     * Get user ID
     *
     * @return mixed
     */
    public function getId(): mixed
    {
        return $this->attributes['id'] ?? null;
    }

    /**
     * Get user attribute
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->attributes[$key] ?? $default;
    }

    /**
     * Set user attribute
     *
     * @param string $key
     * @param mixed $value
     * @return void
     */
    public function set(string $key, mixed $value): void
    {
        $this->attributes[$key] = $value;
    }

    /**
     * Convert to array
     *
     * @return array
     */
    public function toArray(): array
    {
        return $this->attributes;
    }

    /**
     * Get user name
     *
     * @return string|null
     */
    public function getName(): ?string
    {
        return $this->attributes['name'] ?? null;
    }

    /**
     * Get user email
     *
     * @return string|null
     */
    public function getEmail(): ?string
    {
        return $this->attributes['email'] ?? null;
    }
}
