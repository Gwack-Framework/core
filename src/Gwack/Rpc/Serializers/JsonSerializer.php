<?php

namespace Gwack\Rpc\Serializers;

use Gwack\Rpc\Interfaces\SerializerInterface;
use Gwack\Rpc\Exceptions\RpcException;

/**
 * JSON serializer for RPC
 *
 * Optimized JSON serialization with error handling and validation
 * for maximum performance in RPC scenarios.
 *
 * @package Gwack\Rpc\Serializers
 */
class JsonSerializer implements SerializerInterface
{
    /**
     * @var int JSON encoding flags
     */
    private int $encodeFlags;

    /**
     * @var int JSON decoding flags
     */
    private int $decodeFlags;

    /**
     * @var int Maximum recursion depth
     */
    private int $depth;

    /**
     * Constructor
     * 
     * @param int $encodeFlags JSON encoding flags
     * @param int $decodeFlags JSON decoding flags
     * @param int $depth Maximum recursion depth
     */
    public function __construct(
        int $encodeFlags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
        int $decodeFlags = JSON_BIGINT_AS_STRING,
        int $depth = 512
    ) {
        $this->encodeFlags = $encodeFlags;
        $this->decodeFlags = $decodeFlags;
        $this->depth = $depth;
    }

    /**
     * Serialize data for transmission
     * 
     * @param mixed $data The data to serialize
     * @return string The serialized data
     * @throws RpcException If serialization fails
     */
    public function serialize(mixed $data): string
    {
        $result = json_encode($data, $this->encodeFlags, $this->depth);

        if ($result === false) {
            throw new RpcException(
                'JSON serialization failed: ' . json_last_error_msg(),
                -32603
            );
        }

        return $result;
    }

    /**
     * Deserialize data from transmission
     * 
     * @param string $data The serialized data
     * @return mixed The deserialized data
     * @throws RpcException If deserialization fails
     */
    public function deserialize(string $data): mixed
    {
        if (trim($data) === '') {
            throw new RpcException('Empty JSON data provided', -32700);
        }

        $result = json_decode($data, true, $this->depth, $this->decodeFlags);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new RpcException(
                'JSON parsing failed: ' . json_last_error_msg(),
                -32700
            );
        }

        return $result;
    }

    /**
     * Get the content type for this serializer
     * 
     * @return string
     */
    public function getContentType(): string
    {
        return 'application/json';
    }

    /**
     * Check if this serializer can handle the given content type
     * 
     * @param string $contentType
     * @return bool
     */
    public function supports(string $contentType): bool
    {
        $contentType = strtolower(trim($contentType));

        return in_array($contentType, [
            'application/json',
            'application/json-rpc',
            'text/json',
        ], true) || str_contains($contentType, 'json');
    }

    /**
     * Set encoding flags
     * 
     * @param int $flags
     * @return void
     */
    public function setEncodeFlags(int $flags): void
    {
        $this->encodeFlags = $flags;
    }

    /**
     * Set decoding flags
     * 
     * @param int $flags
     * @return void
     */
    public function setDecodeFlags(int $flags): void
    {
        $this->decodeFlags = $flags;
    }

    /**
     * Set recursion depth
     * 
     * @param int $depth
     * @return void
     */
    public function setDepth(int $depth): void
    {
        $this->depth = $depth;
    }
}
