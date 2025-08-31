<?php

namespace Gwack\Rpc\Interfaces;

/**
 * Interface for RPC serializers
 * 
 * Handles serialization and deserialization of RPC requests and responses
 * with support for different formats (JSON, MessagePack, etc.)
 * 
 * @package Gwack\Rpc\Interfaces
 */
interface SerializerInterface
{
    /**
     * Serialize data for transmission
     * 
     * @param mixed $data The data to serialize
     * @return string The serialized data
     */
    public function serialize(mixed $data): string;

    /**
     * Deserialize data from transmission
     * 
     * @param string $data The serialized data
     * @return mixed The deserialized data
     */
    public function deserialize(string $data): mixed;

    /**
     * Get the content type for this serializer
     * 
     * @return string
     */
    public function getContentType(): string;

    /**
     * Check if this serializer can handle the given content type
     * 
     * @param string $contentType
     * @return bool
     */
    public function supports(string $contentType): bool;
}
