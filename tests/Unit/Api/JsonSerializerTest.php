<?php

namespace Tests\Unit\Api;

use PHPUnit\Framework\TestCase;
use Gwack\Api\Serializers\JsonSerializer;

/**
 * Unit tests for JsonSerializer
 */
class JsonSerializerTest extends TestCase
{
    private JsonSerializer $serializer;

    protected function setUp(): void
    {
        $this->serializer = new JsonSerializer();
    }

    /**
     * Test JSON serialization
     */
    public function testJsonSerialization(): void
    {
        $data = ['message' => 'Hello World', 'status' => 200];
        $result = $this->serializer->serialize($data);

        $this->assertIsString($result);
        $this->assertEquals('{"message":"Hello World","status":200}', $result);
    }

    /**
     * Test JSON deserialization
     */
    public function testJsonDeserialization(): void
    {
        $json = '{"message":"Hello World","status":200}';
        $result = $this->serializer->deserialize($json);

        $this->assertIsArray($result);
        $this->assertEquals(['message' => 'Hello World', 'status' => 200], $result);
    }

    /**
     * Test serialization with complex data
     */
    public function testSerializationWithComplexData(): void
    {
        $data = [
            'users' => [
                ['id' => 1, 'name' => 'John Doe'],
                ['id' => 2, 'name' => 'Jane Smith']
            ],
            'meta' => [
                'total' => 2,
                'page' => 1
            ]
        ];

        $result = $this->serializer->serialize($data);
        $decoded = json_decode($result, true);

        $this->assertEquals($data, $decoded);
    }

    /**
     * Test content type support
     */
    public function testContentTypeSupport(): void
    {
        $this->assertTrue($this->serializer->supports('application/json'));
        $this->assertTrue($this->serializer->supports('application/json; charset=utf-8'));
        $this->assertFalse($this->serializer->supports('application/xml'));
    }

    /**
     * Test serialization with special characters
     */
    public function testSerializationWithSpecialCharacters(): void
    {
        $data = ['message' => 'Hello "World" & <Universe>'];
        $result = $this->serializer->serialize($data);

        // The JSON will have escaped quotes but the content should be preserved
        $decoded = json_decode($result, true);
        $this->assertEquals('Hello "World" & <Universe>', $decoded['message']);
    }

    /**
     * Test deserialization error handling
     */
    public function testDeserializationErrorHandling(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('JSON deserialization failed');

        $invalidJson = '{"invalid": json}';
        $this->serializer->deserialize($invalidJson);
    }
}
