<?php

namespace Gwack\Api\Serializers;

use Gwack\Api\Interfaces\SerializerInterface;

/**
 * JSON serializer for API responses
 *
 * Optimized JSON serialization with support for various data types,
 * proper error handling, and performance optimization
 *
 * @package Gwack\Api\Serializers
 */
class JsonSerializer implements SerializerInterface
{
    private int $flags;
    private int $depth;

    /**
     * Constructor
     *
     * @param int $flags JSON encoding flags
     * @param int $depth Maximum recursion depth
     */
    public function __construct(
        int $flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION,
        int $depth = 512
    ) {
        $this->flags = $flags;
        $this->depth = $depth;
    }

    /**
     * Serialize data to JSON string
     *
     * @param mixed $data The data to serialize
     * @param array $options Serialization options
     * @return string The JSON string
     * @throws \RuntimeException If serialization fails
     */
    public function serialize(mixed $data, array $options = []): string
    {
        $flags = $options['flags'] ?? $this->flags;
        $depth = $options['depth'] ?? $this->depth;

        // Handle special cases
        if ($data === null) {
            return 'null';
        }

        // Pre-process data for optimal JSON encoding
        $processedData = $this->preprocessData($data);

        $json = json_encode($processedData, $flags, $depth);

        if ($json === false) {
            throw new \RuntimeException(
                'JSON serialization failed: ' . json_last_error_msg(),
                json_last_error()
            );
        }

        return $json;
    }

    /**
     * Deserialize JSON string to PHP data
     *
     * @param string $data The JSON string
     * @param array $options Deserialization options
     * @return mixed The deserialized data
     * @throws \RuntimeException If deserialization fails
     */
    public function deserialize(string $data, array $options = []): mixed
    {
        $associative = $options['associative'] ?? true;
        $depth = $options['depth'] ?? $this->depth;
        $flags = $options['flags'] ?? JSON_THROW_ON_ERROR;

        try {
            return json_decode($data, $associative, $depth, $flags);
        } catch (\JsonException $e) {
            throw new \RuntimeException(
                'JSON deserialization failed: ' . $e->getMessage(),
                $e->getCode(),
                $e
            );
        }
    }

    /**
     * Get the content type for JSON
     *
     * @return string The MIME content type
     */
    public function getContentType(): string
    {
        return 'application/json';
    }

    /**
     * Check if this serializer supports the given content type
     *
     * @param string $contentType The content type to check
     * @return bool True if supported
     */
    public function supports(string $contentType): bool
    {
        $contentType = strtolower(trim($contentType));

        return in_array($contentType, [
            'application/json',
            'text/json',
            'application/x-json',
        ], true) || str_starts_with($contentType, 'application/json');
    }

    /**
     * Create a pretty-printed JSON serializer
     *
     * @return self A new serializer instance for pretty printing
     */
    public static function pretty(): self
    {
        return new self(
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_PRESERVE_ZERO_FRACTION
        );
    }

    /**
     * Create a compact JSON serializer
     *
     * @return self A new serializer instance for compact output
     */
    public static function compact(): self
    {
        return new self(0);
    }

    /**
     * Pre-process data for optimal JSON encoding
     *
     * @param mixed $data The data to process
     * @return mixed Processed data
     */
    private function preprocessData(mixed $data): mixed
    {
        if (is_array($data)) {
            return array_map([$this, 'preprocessData'], $data);
        }

        if (is_object($data)) {
            return $this->processObject($data);
        }

        if (is_resource($data)) {
            return '[Resource]';
        }

        if (is_float($data) && (is_nan($data) || is_infinite($data))) {
            return null;
        }

        return $data;
    }

    /**
     * Process object for JSON serialization
     *
     * @param object $object The object to process
     * @return mixed Processed object data
     */
    private function processObject(object $object): mixed
    {
        // Handle JsonSerializable objects
        if ($object instanceof \JsonSerializable) {
            return $this->preprocessData($object->jsonSerialize());
        }

        // Handle DateTime objects
        if ($object instanceof \DateTimeInterface) {
            return $object->format(\DateTimeInterface::RFC3339);
        }

        // Handle objects with toArray method
        if (method_exists($object, 'toArray')) {
            return $this->preprocessData($object->toArray());
        }

        // Handle objects with toJson method
        if (method_exists($object, 'toJson')) {
            return json_decode($object->toJson(), true);
        }

        // Convert to array using public properties
        $reflection = new \ReflectionClass($object);
        $data = [];

        foreach ($reflection->getProperties(\ReflectionProperty::IS_PUBLIC) as $property) {
            $data[$property->getName()] = $this->preprocessData($property->getValue($object));
        }

        return $data;
    }

    /**
     * Set JSON encoding flags
     *
     * @param int $flags JSON encoding flags
     * @return self
     */
    public function setFlags(int $flags): self
    {
        $this->flags = $flags;
        return $this;
    }

    /**
     * Set maximum recursion depth
     *
     * @param int $depth Maximum depth
     * @return self
     */
    public function setDepth(int $depth): self
    {
        $this->depth = $depth;
        return $this;
    }

    /**
     * Get current JSON encoding flags
     *
     * @return int Current flags
     */
    public function getFlags(): int
    {
        return $this->flags;
    }

    /**
     * Get current maximum recursion depth
     *
     * @return int Current depth
     */
    public function getDepth(): int
    {
        return $this->depth;
    }
}
