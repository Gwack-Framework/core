<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Gwack\Container\ArrayCache;

/**
 * Tests for the ArrayCache implementation
 * 
 * @package Tests\Unit
 */
class ArrayCacheTest extends TestCase
{
    private ArrayCache $cache;

    protected function setUp(): void
    {
        $this->cache = new ArrayCache();
    }

    public function testCanStoreAndRetrieveReflection(): void
    {
        $data = ['constructor' => [], 'methods' => []];
        $this->cache->storeReflection('TestClass', $data);

        $this->assertTrue($this->cache->hasReflection('TestClass'));
        $this->assertEquals($data, $this->cache->getReflection('TestClass'));
    }

    public function testCanStoreAndRetrieveGeneralData(): void
    {
        $data = ['key' => 'value'];
        $this->cache->store('test-key', $data);

        $this->assertTrue($this->cache->has('test-key'));
        $this->assertEquals($data, $this->cache->get('test-key'));
    }

    public function testReturnsNullForNonExistentData(): void
    {
        $this->assertNull($this->cache->getReflection('NonExistent'));
        $this->assertNull($this->cache->get('non-existent'));
    }

    public function testCanForgetData(): void
    {
        $this->cache->store('test', 'value');
        $this->assertTrue($this->cache->has('test'));

        $this->cache->forget('test');
        $this->assertFalse($this->cache->has('test'));
    }

    public function testCanFlushAllData(): void
    {
        $this->cache->storeReflection('Class1', []);
        $this->cache->store('key1', 'value1');

        $this->cache->flush();

        $this->assertFalse($this->cache->hasReflection('Class1'));
        $this->assertFalse($this->cache->has('key1'));
    }

    public function testTracksHitRatio(): void
    {
        $this->cache->store('key', 'value');

        // Miss
        $this->cache->get('non-existent');

        // Hit
        $this->cache->get('key');

        $stats = $this->cache->getStats();
        $this->assertEquals(1, $stats['hits']);
        $this->assertEquals(1, $stats['misses']);
        $this->assertEquals(0.5, $this->cache->getHitRatio());
    }
}
