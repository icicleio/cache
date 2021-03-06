<?php
namespace Icicle\Tests\Cache;

use Icicle\Cache\MemoryCache;
use Icicle\Coroutine as CoroutineNS;
use Icicle\Coroutine\Coroutine;
use Icicle\Loop;

class MemoryCacheTestException extends \Exception {}

class MemoryCacheTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var \Icicle\Cache\Cache
     */
    protected $cache;

    public function setUp()
    {
        $this->cache = new MemoryCache();
    }

    public function testExistsNonExistentKey()
    {
        $coroutine = new Coroutine($this->cache->exists('key'));

        $this->assertFalse($coroutine->wait());
    }

    public function testGetNonExistentKey()
    {
        $coroutine = new Coroutine($this->cache->get('key'));

        $this->assertNull($coroutine->wait());
    }

    public function testDeleteNonExistentKey()
    {
        $coroutine = new Coroutine($this->cache->delete('key'));
    
        $this->assertTrue($coroutine->wait());
    }

    /**
     * @depends testExistsNonExistentKey
     */
    public function testSetNonExistentKey()
    {
        $coroutine = CoroutineNS\create(function () {
            $key = 'key';
            $value = 'value';

            $this->assertFalse(yield from $this->cache->exists($key));

            $this->assertTrue(yield from $this->cache->set($key, $value));

            $this->assertTrue(yield from $this->cache->exists($key));
            $this->assertSame($value, yield from $this->cache->get($key));
        });

        $coroutine->wait();
    }

    /**
     * @depends testSetNonExistentKey
     */
    public function testSetExistentKey()
    {
        $coroutine = CoroutineNS\create(function () {
            $key = 'key';
            $value1 = 'value1';
            $value2 = 'value2';

            yield from $this->cache->set($key, $value1);

            $this->assertSame($value1, (yield from $this->cache->get($key)));

            yield from $this->cache->set($key, $value2);

            $this->assertTrue(yield from $this->cache->exists($key));
            $this->assertSame($value2, yield from $this->cache->get($key));
        });

        $coroutine->wait();
    }

    /**
     * @depends testSetNonExistentKey
     */
    public function testSetWithExpiration()
    {
        $coroutine = CoroutineNS\create(function () {
            $key = 'key';
            $value = 'value';
            $expiration = 1;

            yield from $this->cache->set($key, $value, $expiration);

            yield from CoroutineNS\sleep($expiration);

            $this->assertFalse(yield from $this->cache->exists($key));
        });

        $coroutine->wait();
    }

    /**
     * @depends testSetWithExpiration
     */
    public function testSetAgainWithExpiration()
    {
        $coroutine = CoroutineNS\create(function () {
            $key = 'key';
            $value1 = 'value1';
            $value2 = 'value2';
            $expiration = 1;

            yield from $this->cache->set($key, $value1);

            yield from CoroutineNS\sleep($expiration);

            $this->assertTrue(yield from $this->cache->exists($key));

            yield from $this->cache->set($key, $value2, $expiration);

            yield from CoroutineNS\sleep($expiration);

            $this->assertFalse(yield from $this->cache->exists($key));
        });

        $coroutine->wait();
    }

    public function testSetAgainWithoutExpiration()
    {
        $coroutine = CoroutineNS\create(function () {
            $key = 'key';
            $value1 = 'value1';
            $value2 = 'value2';
            $expiration = 1;

            yield from $this->cache->set($key, $value1, $expiration);

            $this->assertTrue(yield from $this->cache->exists($key));

            yield from $this->cache->set($key, $value2);

            yield from CoroutineNS\sleep($expiration);

            $this->assertTrue(yield from $this->cache->exists($key));
        });

        $coroutine->wait();
    }

    /**
     * @depends testSetWithExpiration
     */
    public function testSetAgainWithDifferentExpiration()
    {
        $coroutine = CoroutineNS\create(function () {
            $key = 'key';
            $value1 = 'value1';
            $value2 = 'value2';
            $expiration1 = 3;
            $expiration2 = 1;

            yield from $this->cache->set($key, $value1, $expiration1);

            yield from CoroutineNS\sleep($expiration2);

            $this->assertTrue(yield from $this->cache->exists($key));

            yield from $this->cache->set($key, $value2, $expiration2);

            yield from CoroutineNS\sleep($expiration2);

            $this->assertFalse(yield from $this->cache->exists($key));
        });

        $coroutine->wait();
    }

    /**
     * @depends testSetWithExpiration
     */
    public function testGetResetsExpiration()
    {
        $coroutine = CoroutineNS\create(function () {
            $key = 'key';
            $value = 'value';
            $expiration = 3;
            $sleep = 1;

            yield from $this->cache->set($key, $value, $expiration);

            yield from CoroutineNS\sleep($sleep);

            $this->assertTrue(yield from$this->cache->exists($key));
            $this->assertSame($value, yield from $this->cache->get($key));

            yield from CoroutineNS\sleep($sleep);

            $this->assertTrue(yield from $this->cache->exists($key));
            $this->assertSame($value, yield from $this->cache->get($key));

            yield from CoroutineNS\sleep($expiration);

            $this->assertFalse(yield from $this->cache->exists($key));
        });

        $coroutine->wait();
    }

    /**
     * @depends testSetNonExistentKey
     * @depends testDeleteNonExistentKey
     */
    public function testDeleteExistentKey()
    {
        $coroutine = CoroutineNS\create(function () {
            $key = 'key';
            $value = 'value';

            yield from $this->cache->set($key, $value);

            $this->assertTrue(yield from $this->cache->delete($key));

            $this->assertFalse(yield from $this->cache->exists($key));
        });

        $coroutine->wait();
    }

    /**
     * @depends testExistsNonExistentKey
     */
    public function testAddNonExistentKey()
    {
        $coroutine = CoroutineNS\create(function () {
            $key = 'key';
            $value = 'value';

            $this->assertFalse(yield from $this->cache->exists($key));

            $this->assertTrue(yield from $this->cache->add($key, $value));

            $this->assertTrue(yield from $this->cache->exists($key));
        });

        $coroutine->wait();
    }
    
    /**
     * @depends testAddNonExistentKey
     */
    public function testAddExistentKey()
    {
        $coroutine = CoroutineNS\create(function () {
            $key = 'key';
            $value = 'value';

            $this->assertTrue(yield from $this->cache->add($key, $value));

            $this->assertFalse(yield from $this->cache->add($key, $value));
        });
        
        $coroutine->wait();
    }

    /**
     * @depends testExistsNonExistentKey
     */
    public function testReplaceNonExistentKey()
    {
        $coroutine = CoroutineNS\create(function () {
            $key = 'key';
            $value = 'value';

            $this->assertFalse(yield from $this->cache->exists($key));

            $this->assertFalse(yield from $this->cache->replace($key, $value));

            $this->assertFalse(yield from $this->cache->exists($key));
        });

        $coroutine->wait();
    }

    /**
     * @depends testReplaceNonExistentKey
     * @depends testAddNonExistentKey
     */
    public function testReplaceExistentKey()
    {
        $coroutine = CoroutineNS\create(function () {
            $key = 'key';
            $value1 = 'value1';
            $value2 = 'value2';

            yield from $this->cache->add($key, $value1);

            $this->assertTrue(yield from $this->cache->replace($key, $value2));

            $this->assertTrue(yield from $this->cache->exists($key));
            $this->assertSame($value2, yield from $this->cache->get($key));
        });

        $coroutine->wait();
    }

    public function testUpdate()
    {
        $key = 'key';
        $value = 'value';

        $coroutine = CoroutineNS\create(function () use ($key, $value) {
            $result = yield from $this->cache->update($key, function ($current) use ($key, $value) {
                $this->assertNull($current);
                return $value;
            });

            $this->assertSame($value, $result);
            $this->assertSame($value, yield from $this->cache->get($key));
        });

        $coroutine->wait();
    }

    /**
     * @depends testUpdate
     */
    public function testGetDuringUpdate()
    {
        $key = 'key';
        $timeout = 0.1;
        $value = 'value';

        $start = microtime(true);

        $coroutine1 = CoroutineNS\create(function () use ($key, $timeout, $value) {
            yield from $this->cache->update($key, function () use ($key, $timeout, $value) {
                yield from CoroutineNS\sleep($timeout);
                return $value;
            });
        });
        $coroutine1->done();

        $coroutine2 = CoroutineNS\create(function () use ($start, $key, $timeout, $value) {
            yield from CoroutineNS\sleep(0); // Sleep for short time to allow first coroutine to enter synchronized().
            $result = yield from $this->cache->get($key); // Should wait while coroutine in update() is sleeping.
            $this->assertGreaterThan($timeout, microtime(true) - $start);
            $this->assertSame($value, $result);
        });
        $coroutine2->done();

        Loop\run();
    }

    /**
     * @depends testUpdate
     */
    public function testUpdateDuringUpdate()
    {
        $key = 'key';
        $timeout = 0.1;
        $value1 = 'value1';
        $value2 = 'value2';

        $start = microtime(true);

        $coroutine1 = CoroutineNS\create(function () use ($key, $timeout, $value1) {
            yield from $this->cache->update($key, function () use ($key, $timeout, $value1) {
                yield from CoroutineNS\sleep($timeout);
                return $value1;
            });
        });
        $coroutine1->done();

        $coroutine2 = CoroutineNS\create(function () use ($start, $key, $timeout, $value1, $value2) {
            yield from CoroutineNS\sleep(0); // Sleep for short time to allow first coroutine to enter synchronized().
            $result = yield from $this->cache->update($key, function ($current) use ($value1, $value2) {
                $this->assertSame($value1, $current);
                return $value2;
            }); // Should wait while coroutine in update() is sleeping.
            $this->assertGreaterThan($timeout, microtime(true) - $start);
            $this->assertSame($value2, $result);
        });
        $coroutine2->done();

        Loop\run();
    }

    /**
     * @depends testUpdate
     */
    public function testUpdateWithThrow()
    {
        $key = 'key';
        $value = 'value';
        $exception = new MemoryCacheTestException();

        $coroutine1 = CoroutineNS\create(function () use ($key, $value, $exception) {
            yield from $this->cache->set($key, $value);
            yield from $this->cache->update($key, function () use ($key, $exception) {
                throw $exception;
                yield; // Unreachable, but makes function a coroutine.
            });
        });

        try {
            $coroutine1->wait();
        } catch (MemoryCacheTestException $reason) {
            $this->assertSame($exception, $reason);
        }

        $coroutine2 = new Coroutine($this->cache->get($key));

        $this->assertSame($value, $coroutine2->wait());
    }
}
