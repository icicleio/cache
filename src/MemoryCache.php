<?php
namespace Icicle\Cache;

use Icicle\Awaitable\Awaitable;
use Icicle\Awaitable\Delayed;
use Icicle\Coroutine;
use Icicle\Exception\InvalidArgumentError;
use Icicle\Loop;
use Icicle\Loop\Watcher\Timer;

class MemoryCache implements Cache
{
    /**
     * @var \Icicle\Awaitable\Delayed[]
     */
    private $locks = [];

    /**
     * @var mixed[]
     */
    private $data = [];

    /**
     * @var Timer[]
     */
    private $timers = [];

    /**
     * @var callable
     */
    private $timerCallback;

    public function __construct()
    {
        $this->timerCallback = Coroutine\wrap(function (Timer $timer): \Generator {
            $key = $timer->getData();

            yield from $this->wait($key); // Wait if key is locked.

            // Delete only if value has not been changed.
            if (isset($this->timers[$key]) && $timer === $this->timers[$key]) {
                yield from $this->delete($key);
            }
        });
    }

    /**
     * {@inheritdoc}
     */
    public function exists(string $key): \Generator
    {
        yield from $this->wait($key);

        return array_key_exists($key, $this->data);
    }

    /**
     * {@inheritdoc}
     */
    public function get(string $key): \Generator
    {
        yield from $this->wait($key);

        return $this->fetch($key);
    }

    /**
     * {@inheritdoc}
     */
    public function set(string $key, $value, int $expiration = 0): \Generator
    {
        yield from $this->wait($key);

        $this->put($key, $value, $expiration);

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function add(string $key, $value, int $expiration = 0): \Generator
    {
        yield from $this->wait($key);

        if (isset($this->data[$key])) {
            return false;
        }

        $this->put($key, $value, $expiration);

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function replace(string $key, $value, int $expiration = 0): \Generator
    {
        yield from $this->wait($key);

        if (!isset($this->data[$key])) {
            return false;
        }

        $this->put($key, $value, $expiration);

        return true;
    }

    /**
     * @param string $key
     *
     * @return mixed
     */
    protected function fetch(string $key)
    {
        if (!isset($this->data[$key])) {
            return;
        }

        // Reset timer if there was an expiration.
        if (isset($this->timers[$key])) {
            $this->timers[$key]->again();
        }

        return $this->data[$key];
    }

    /**
     * @param string $key
     * @param mixed $value
     * @param int $expiration
     */
    protected function put(string $key, $value, int $expiration = 0)
    {
        $this->data[$key] = $value;

        if (isset($this->timers[$key])) {
            $this->timers[$key]->stop();
        }

        if ($expiration) {
            $this->timers[$key] = Loop\timer($expiration, $this->timerCallback, $key);
        } else {
            unset($this->timers[$key]);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function delete(string $key): \Generator
    {
        yield from $this->wait($key);

        unset($this->data[$key]);

        if (isset($this->timers[$key])) {
            $this->timers[$key]->stop();
            unset($this->timers[$key]);
        }

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function update(string $key, callable $callback, int $expiration = 0): \Generator
    {
        yield from $this->wait($key);

        $this->lock($key);

        try {
            $result = $callback(isset($this->data[$key]) ? $this->data[$key] : null);

            if ($result instanceof \Generator) {
                $result = yield from $result;
            } elseif ($result instanceof Awaitable) {
                $result = yield $result;
            }

            $this->put($key, $result, $expiration);
        } finally {
            $this->unlock($key);
        }

        return $result;
    }

    /**
     * Locks the given key until unlock() is called.
     *
     * @param string $key
     *
     * @throws \Icicle\Exception\InvalidArgumentError
     */
    protected function lock(string $key)
    {
        if (isset($this->locks[$key])) {
            throw new InvalidArgumentError('Key was already locked.');
        }

        $this->locks[$key] = new Delayed();
    }

    /**
     * Unlocks the given key. Throws if the key was not locked.
     *
     * @param string $key
     *
     * @throws \Icicle\Exception\InvalidArgumentError
     */
    protected function unlock(string $key)
    {
        if (!isset($this->locks[$key])) {
            throw new InvalidArgumentError('No lock was set on the given key.');
        }

        $awaitable = $this->locks[$key];
        unset($this->locks[$key]);
        $awaitable->resolve();
    }

    /**
     * @coroutine
     *
     * @param string $key
     *
     * @return \Generator
     *
     * @resolve bool
     */
    protected function wait(string $key): \Generator
    {
        if (isset($this->locks[$key])) {
            do {
                yield $this->locks[$key];
            } while (isset($this->locks[$key]));

            return true;
        }

        return false;
    }
}
