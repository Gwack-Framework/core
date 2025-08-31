<?php

namespace Gwack\Api\Interfaces;

/**
 * Interface for API resource serialization
 *
 * Handles conversion between PHP objects/arrays and HTTP response formats
 *
 * @package Gwack\Api\Interfaces
 */
interface SerializerInterface
{
    /**
     * Serialize data to string format
     *
     * @param mixed $data The data to serialize
     * @param array $options Serialization options
     * @return string The serialized data
     */
    public function serialize(mixed $data, array $options = []): string;

    /**
     * Deserialize string data to PHP format
     *
     * @param string $data The serialized data
     * @param array $options Deserialization options
     * @return mixed The deserialized data
     */
    public function deserialize(string $data, array $options = []): mixed;

    /**
     * Get the content type for this serializer
     *
     * @return string The MIME content type
     */
    public function getContentType(): string;

    /**
     * Check if this serializer supports the given content type
     *
     * @param string $contentType The content type to check
     * @return bool True if supported
     */
    public function supports(string $contentType): bool;
}
