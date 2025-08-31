<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Gwack\Rpc\Handlers\ServiceHandler;
use Gwack\Rpc\Exceptions\MethodNotFoundException;
use Gwack\Rpc\Exceptions\InvalidParamsException;

/**
 * Tests for the ServiceHandler
 * 
 * @package Tests\Unit
 */
class ServiceHandlerTest extends TestCase
{
    private ServiceHandler $handler;
    private TestMathService $service;

    protected function setUp(): void
    {
        $this->service = new TestMathService();
        $this->handler = new ServiceHandler($this->service);
    }

    public function testCanHandleMethodCall(): void
    {
        $result = $this->handler->handleCall('add', [5, 3]);
        $this->assertEquals(8, $result);
    }

    public function testCanHandleNamedParameters(): void
    {
        $result = $this->handler->handleCall('subtract', ['a' => 10, 'b' => 4]);
        $this->assertEquals(6, $result);
    }

    public function testCanGetMethods(): void
    {
        $methods = $this->handler->getMethods();
        $this->assertContains('add', $methods);
        $this->assertContains('subtract', $methods);
        $this->assertContains('multiply', $methods);
    }

    public function testCanCheckMethodExists(): void
    {
        $this->assertTrue($this->handler->hasMethod('add'));
        $this->assertFalse($this->handler->hasMethod('nonexistent'));
    }

    public function testThrowsExceptionForNonExistentMethod(): void
    {
        $this->expectException(MethodNotFoundException::class);
        $this->handler->handleCall('nonexistent', []);
    }

    public function testCanGetMethodMetadata(): void
    {
        $metadata = $this->handler->getMethodMetadata('add');

        $this->assertEquals('add', $metadata['name']);
        $this->assertArrayHasKey('parameters', $metadata);
        $this->assertCount(2, $metadata['parameters']);
    }

    public function testCanHandleOptionalParameters(): void
    {
        $result = $this->handler->handleCall('power', [2]);
        $this->assertEquals(4, $result); // 2^2 (default exponent is 2)
    }

    public function testCanHandleDefaultValues(): void
    {
        $result = $this->handler->handleCall('greet', ['Alice']);
        $this->assertEquals('Hello, Alice!', $result);
    }

    public function testThrowsExceptionForMissingRequiredParams(): void
    {
        $this->expectException(InvalidParamsException::class);
        $this->handler->handleCall('add', [5]); // Missing second parameter
    }

    public function testCanSetAllowedMethods(): void
    {
        $this->handler->setAllowedMethods(['add', 'subtract']);

        $this->assertTrue($this->handler->hasMethod('add'));
        $this->assertTrue($this->handler->hasMethod('subtract'));
        $this->assertFalse($this->handler->hasMethod('multiply'));
    }

    public function testCanSetDeniedMethods(): void
    {
        $this->handler->setDeniedMethods(['multiply']);

        $this->assertTrue($this->handler->hasMethod('add'));
        $this->assertFalse($this->handler->hasMethod('multiply'));
    }

    public function testSupportsWildcardPatterns(): void
    {
        $this->handler->setAllowedMethods(['get*']);

        $this->assertTrue($this->handler->hasMethod('getValue'));
        $this->assertFalse($this->handler->hasMethod('add'));
    }
}

// Test service class
class TestMathService
{
    public function add(int $a, int $b): int
    {
        return $a + $b;
    }

    public function subtract(int $a, int $b): int
    {
        return $a - $b;
    }

    public function multiply(int $a, int $b): int
    {
        return $a * $b;
    }

    public function power(int $base, int $exponent = 2): int
    {
        return pow($base, $exponent);
    }

    public function greet(string $name, string $greeting = 'Hello'): string
    {
        return "{$greeting}, {$name}!";
    }

    public function getValue(): string
    {
        return 'test-value';
    }

    private function privateMethod(): void
    {
        // This should not be accessible
    }

    protected function protectedMethod(): void
    {
        // This should not be accessible
    }
}
