<?php
namespace Icicle\Cache;

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
        $this->timerCallback = Coroutine\wrap(function (Timer $timer) {
            $key = $timer->getData();

            yield $this->wait($key); // Wait if key is locked.

            // Delete only if value has not been changed.
            if (isset($this->timers[$key]) && $timer === $this->timers[$key]) {
                yield $this->delete($key);
            }
        });
    }

    /**
     * {@inheritdoc}
     */
    public function exists($key)
    {
        $key = (string) $key;

        yield $this->wait($key);

        yield array_key_exists($key, $this->data);
    }

    /**
     * {@inheritdoc}
     */
    public function get($key)
    {
        $key = (string) $key;

        yield $this->wait($key);

        yield $this->fetch($key);
    }

    /**
     * {@inheritdoc}
     */
    public function set($key, $value, $expiration = 0)
    {
        $key = (string) $key;

        yield $this->wait($key);

        $this->put($key, $value, $expiration);

        yield true;
    }

    /**
     * {@inheritdoc}
     */
    public function add($key, $value, $expiration = 0)
    {
        $key = (string) $key;

        yield $this->wait($key);

        if (isset($this->data[$key])) {
            yield false;
            return;
        }

        $this->put($key, $value, $expiration);

        yield true;
    }

    /**
     * {@inheritdoc}
     */
    public function replace($key, $value, $expiration = 0)
    {
        $key = (string) $key;

        yield $this->wait($key);

        if (!isset($this->data[$key])) {
            yield false;
            return;
        }

        $this->put($key, $value, $expiration);

        yield true;
    }

    /**
     * @param string $key
     *
     * @return mixed
     */
    protected function fetch($key)
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
    protected function put($key, $value, $expiration = 0)
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
    public function delete($key)
    {
        $key = (string) $key;

        yield $this->wait($key);

        unset($this->data[$key]);

        if (isset($this->timers[$key])) {
            $this->timers[$key]->stop();
            unset($this->timers[$key]);
        }

        yield true;
    }

    /**
     * {@inheritdoc}
     */
    public function update($key, callable $callback, $expiration = 0)
    {
        $key = (string) $key;

        yield $this->wait($key);

        $this->lock($key);

        try {
            $result = (yield $callback(isset($this->data[$key]) ? $this->data[$key] : null));
            $this->put($key, $result, $expiration);
        } finally {
            $this->unlock($key);
        }

        yield $result;
    }

    /**
     * Locks the given key until unlock() is called.
     *
     * @param string $key
     *
     * @throws \Icicle\Exception\InvalidArgumentError
     */
    protected function lock($key)
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
    protected function unlock($key)
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
    protected function wait($key)
    {
        if (isset($this->locks[$key])) {
            do {
                yield $this->locks[$key];
            } while (isset($this->locks[$key]));

            yield true;
            return;
        }

        yield false;
    }
}
