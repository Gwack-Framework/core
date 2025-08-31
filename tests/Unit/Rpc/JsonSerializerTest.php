<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Gwack\Rpc\Serializers\JsonSerializer;
use Gwack\Rpc\Exceptions\RpcException;

/**
 * Tests for the JsonSerializer
 * 
 * @package Tests\Unit
 */
class JsonSerializerTest extends TestCase
{
    private JsonSerializer $serializer;

    protected function setUp(): void
    {
        $this->serializer = new JsonSerializer();
    }

    public function testCanSerializeSimpleData(): void
    {
        $data = ['test' => 'value', 'number' => 42];
        $result = $this->serializer->serialize($data);

        $this->assertIsString($result);
        $this->assertJson($result);
    }

    public function testCanDeserializeValidJson(): void
    {
        $json = '{"test":"value","number":42}';
        $result = $this->serializer->deserialize($json);

        $this->assertEquals(['test' => 'value', 'number' => 42], $result);
    }

    public function testThrowsExceptionForInvalidJson(): void
    {
        $this->expectException(RpcException::class);
        $this->expectExceptionCode(-32700);

        $this->serializer->deserialize('invalid json');
    }

    public function testThrowsExceptionForEmptyData(): void
    {
        $this->expectException(RpcException::class);
        $this->expectExceptionCode(-32700);

        $this->serializer->deserialize('');
    }

    public function testGetContentType(): void
    {
        $this->assertEquals('application/json', $this->serializer->getContentType());
    }

    public function testSupportsJsonContentTypes(): void
    {
        $this->assertTrue($this->serializer->supports('application/json'));
        $this->assertTrue($this->serializer->supports('application/json-rpc'));
        $this->assertTrue($this->serializer->supports('text/json'));
        $this->assertFalse($this->serializer->supports('text/xml'));
    }

    public function testCanHandleComplexData(): void
    {
        $data = [
            'array' => [1, 2, 3],
            'object' => ['nested' => 'value'],
            'null' => null,
            'boolean' => true,
            'unicode' => 'unicode string: 中文',
        ];

        $serialized = $this->serializer->serialize($data);
        $deserialized = $this->serializer->deserialize($serialized);

        $this->assertEquals($data, $deserialized);
    }

    public function testCanSetCustomFlags(): void
    {
        $this->serializer->setEncodeFlags(JSON_PRETTY_PRINT);

        $data = ['test' => 'value'];
        $result = $this->serializer->serialize($data);

        $this->assertStringContainsString("\n", $result);
    }
}
