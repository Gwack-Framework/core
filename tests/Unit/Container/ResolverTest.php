<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Gwack\Container\Container;
use Gwack\Container\Resolver;
use Gwack\Container\ArrayCache;
use Gwack\Container\Exceptions\BindingResolutionException;
use Gwack\Container\Exceptions\CircularDependencyException;

/**
 * Tests for the Resolver class
 * 
 * @package Tests\Unit
 */
class ResolverTest extends TestCase
{
    private Container $container;
    private Resolver $resolver;
    private ArrayCache $cache;

    protected function setUp(): void
    {
        $this->container = new Container();
        $this->cache = new ArrayCache();
        $this->resolver = new Resolver($this->container, $this->cache);
    }

    public function testCanResolveSimpleClass(): void
    {
        $instance = $this->resolver->resolve(SimpleTestClass::class);
        $this->assertInstanceOf(SimpleTestClass::class, $instance);
    }

    public function testCanBuildClassWithDependencies(): void
    {
        $this->container->bind(TestDependencyInterface::class, ConcreteTestDependency::class);

        $instance = $this->resolver->build(ClassWithTestDependency::class);
        $this->assertInstanceOf(ClassWithTestDependency::class, $instance);
        $this->assertInstanceOf(ConcreteTestDependency::class, $instance->dependency);
    }

    public function testCanResolveConstructorDependencies(): void
    {
        $this->container->bind(TestDependencyInterface::class, ConcreteTestDependency::class);

        $dependencies = $this->resolver->resolveConstructorDependencies(ClassWithTestDependency::class);
        $this->assertCount(1, $dependencies);
        $this->assertInstanceOf(ConcreteTestDependency::class, $dependencies[0]);
    }

    public function testCanResolveMethodDependencies(): void
    {
        $this->container->bind(TestDependencyInterface::class, ConcreteTestDependency::class);

        $method = new \ReflectionMethod(ClassWithTestMethods::class, 'methodWithDependency');
        $dependencies = $this->resolver->resolveMethodDependencies($method);

        $this->assertCount(1, $dependencies);
        $this->assertInstanceOf(ConcreteTestDependency::class, $dependencies[0]);
    }

    public function testCanHandleParametersInMethodResolution(): void
    {
        $method = new \ReflectionMethod(ClassWithTestMethods::class, 'methodWithParameters');
        $dependencies = $this->resolver->resolveMethodDependencies($method, [
            'name' => 'test-name',
            'value' => 42
        ]);

        $this->assertEquals(['test-name', 42], $dependencies);
    }

    public function testCanHandleDefaultValuesInMethodResolution(): void
    {
        $method = new \ReflectionMethod(ClassWithTestMethods::class, 'methodWithDefaults');
        $dependencies = $this->resolver->resolveMethodDependencies($method);

        $this->assertEquals(['default-name', 100], $dependencies);
    }

    public function testThrowsExceptionForUnresolvableParameter(): void
    {
        $this->expectException(BindingResolutionException::class);

        $method = new \ReflectionMethod(ClassWithTestMethods::class, 'methodWithUnresolvableParameter');
        $this->resolver->resolveMethodDependencies($method);
    }

    public function testCanDetectCircularDependencies(): void
    {
        $this->container->bind('service-a', function ($container) {
            return $container->get('service-b');
        });

        $this->container->bind('service-b', function ($container) {
            return $container->get('service-a');
        });

        $this->expectException(CircularDependencyException::class);
        $this->resolver->resolve('service-a');
    }

    public function testThrowsExceptionForNonInstantiableClass(): void
    {
        $this->expectException(BindingResolutionException::class);
        $this->resolver->build(AbstractTestClass::class);
    }

    public function testThrowsExceptionForNonExistentClass(): void
    {
        $this->expectException(BindingResolutionException::class);
        $this->resolver->build('NonExistentClass');
    }
}

// Test helper classes
class SimpleTestClass
{
    public function getValue(): string
    {
        return 'simple-test-value';
    }
}

interface TestDependencyInterface
{
    public function getValue(): string;
}

class ConcreteTestDependency implements TestDependencyInterface
{
    public function getValue(): string
    {
        return 'test-dependency-value';
    }
}

class ClassWithTestDependency
{
    public function __construct(public TestDependencyInterface $dependency)
    {
    }
}

class ClassWithTestMethods
{
    public function methodWithDependency(TestDependencyInterface $dep): string
    {
        return $dep->getValue();
    }

    public function methodWithParameters(string $name, int $value): array
    {
        return [$name, $value];
    }

    public function methodWithDefaults(string $name = 'default-name', int $value = 100): array
    {
        return [$name, $value];
    }

    public function methodWithUnresolvableParameter(UnresolvableClass $param): void
    {
        // This should fail
    }
}

abstract class AbstractTestClass
{
    abstract public function test(): void;
}

class UnresolvableClass
{
    public function __construct(string $requiredParam)
    {
    }
}
