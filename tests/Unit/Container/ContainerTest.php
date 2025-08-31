<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Gwack\Container\Container;
use Gwack\Container\ArrayCache;
use Gwack\Container\Exceptions\NotFoundException;
use Gwack\Container\Exceptions\CircularDependencyException;
use Gwack\Container\Exceptions\BindingResolutionException;

/**
 * Comprehensive tests for the Container class
 * 
 * @package Tests\Unit
 */
class ContainerTest extends TestCase
{
    private Container $container;

    protected function setUp(): void
    {
        $this->container = new Container();
    }

    public function testCanCreateContainer(): void
    {
        $this->assertInstanceOf(Container::class, $this->container);
    }

    public function testCanBindAndResolveService(): void
    {
        $this->container->bind('test', function () {
            return 'test-value';
        });

        $this->assertTrue($this->container->has('test'));
        $this->assertEquals('test-value', $this->container->get('test'));
    }

    public function testCanBindSingleton(): void
    {
        $this->container->singleton('singleton-test', function () {
            return new \stdClass();
        });

        $instance1 = $this->container->get('singleton-test');
        $instance2 = $this->container->get('singleton-test');

        $this->assertSame($instance1, $instance2);
    }

    public function testCanBindInstance(): void
    {
        $instance = new \stdClass();
        $instance->value = 'test';

        $this->container->instance('instance-test', $instance);

        $retrieved = $this->container->get('instance-test');
        $this->assertSame($instance, $retrieved);
        $this->assertEquals('test', $retrieved->value);
    }

    public function testCanAutoResolveClass(): void
    {
        $instance = $this->container->get(SimpleClass::class);
        $this->assertInstanceOf(SimpleClass::class, $instance);
    }

    public function testCanResolveDependencies(): void
    {
        $this->container->bind(DependencyInterface::class, ConcreteDependency::class);

        $instance = $this->container->get(ClassWithDependency::class);
        $this->assertInstanceOf(ClassWithDependency::class, $instance);
        $this->assertInstanceOf(ConcreteDependency::class, $instance->dependency);
    }

    public function testCanMakeNewInstance(): void
    {
        $this->container->singleton(SimpleClass::class);

        $singleton1 = $this->container->get(SimpleClass::class);
        $singleton2 = $this->container->get(SimpleClass::class);
        $newInstance = $this->container->make(SimpleClass::class);

        $this->assertSame($singleton1, $singleton2);
        $this->assertNotSame($singleton1, $newInstance);
    }

    public function testCanCallMethodWithDependencyInjection(): void
    {
        $this->container->bind(DependencyInterface::class, ConcreteDependency::class);

        $result = $this->container->call([new ClassWithMethods(), 'methodWithDependency']);
        $this->assertEquals('dependency-result', $result);
    }

    public function testCanCallClosureWithDependencyInjection(): void
    {
        $this->container->bind(DependencyInterface::class, ConcreteDependency::class);

        $result = $this->container->call(function (DependencyInterface $dep) {
            return $dep->getValue();
        });

        $this->assertEquals('dependency-value', $result);
    }

    public function testThrowsNotFoundExceptionForUnknownService(): void
    {
        $this->expectException(NotFoundException::class);
        $this->container->get('non-existent-service');
    }

    public function testCanDetectCircularDependency(): void
    {
        $this->container->bind('service-a', function ($container) {
            return $container->get('service-b');
        });

        $this->container->bind('service-b', function ($container) {
            return $container->get('service-a');
        });

        $this->expectException(CircularDependencyException::class);
        $this->container->get('service-a');
    }

    public function testCanUnbindService(): void
    {
        $this->container->bind('test-service', 'test-value');
        $this->assertTrue($this->container->bound('test-service'));

        $this->container->unbind('test-service');
        $this->assertFalse($this->container->bound('test-service'));
    }

    public function testCanFlushContainer(): void
    {
        $this->container->bind('test1', 'value1');
        $this->container->bind('test2', 'value2');

        $this->container->flush();

        $this->assertFalse($this->container->bound('test1'));
        $this->assertFalse($this->container->bound('test2'));
    }

    public function testCanSetAndUseAlias(): void
    {
        $this->container->bind('original-service', function () {
            return 'test-value';
        });
        $this->container->alias('alias-service', 'original-service');

        $this->assertEquals('test-value', $this->container->get('alias-service'));
    }

    public function testCanTagAndResolveServices(): void
    {
        $this->container->bind('service1', function () {
            return 'value1';
        });
        $this->container->bind('service2', function () {
            return 'value2';
        });

        $this->container->tag(['service1', 'service2'], 'test-tag');

        $tagged = $this->container->tagged('test-tag');
        $this->assertCount(2, $tagged);
        $this->assertContains('value1', $tagged);
        $this->assertContains('value2', $tagged);
    }

    public function testCanGetStats(): void
    {
        $this->container->bind('test', 'value');
        $this->container->alias('alias', 'test');

        $stats = $this->container->getStats();

        $this->assertIsArray($stats);
        $this->assertGreaterThan(0, $stats['bindings']);
        $this->assertGreaterThan(0, $stats['aliases']);
    }

    public function testCanHandleOptionalParameters(): void
    {
        $instance = $this->container->get(ClassWithOptionalParameter::class);
        $this->assertInstanceOf(ClassWithOptionalParameter::class, $instance);
        $this->assertNull($instance->optional);
    }

    public function testCanHandleDefaultValues(): void
    {
        $instance = $this->container->get(ClassWithDefaultValue::class);
        $this->assertInstanceOf(ClassWithDefaultValue::class, $instance);
        $this->assertEquals('default', $instance->value);
    }
}

// Test helper classes
class SimpleClass
{
    public function getValue(): string
    {
        return 'simple-value';
    }
}

interface DependencyInterface
{
    public function getValue(): string;
}

class ConcreteDependency implements DependencyInterface
{
    public function getValue(): string
    {
        return 'dependency-value';
    }
}

class ClassWithDependency
{
    public function __construct(public DependencyInterface $dependency)
    {
    }
}

class ClassWithMethods
{
    public function methodWithDependency(DependencyInterface $dep): string
    {
        return 'dependency-result';
    }
}

class ClassWithOptionalParameter
{
    public function __construct(public ?DependencyInterface $optional = null)
    {
    }
}

class ClassWithDefaultValue
{
    public function __construct(public string $value = 'default')
    {
    }
}
