<?php
namespace Icicle\Cache;

interface Cache
{
    /**
     * @coroutine
     *
     * @param string $key
     *
     * @return \Generator
     *
     * @resolve bool
     */
    public function exists(string $key): \Generator;

    /**
     * @coroutine
     *
     * @param string $key
     *
     * @return \Generator
     *
     * @resolve mixed|null Null returned if the key did not exist.
     */
    public function get(string $key): \Generator;

    /**
     * @coroutine
     *
     * Sets the key to the given value. If the $expiration param is non-zero, the value will be automatically deleted
     * after $expiration seconds.
     *
     * @param string $key
     * @param mixed $value
     * @param float $expiration
     *
     * @return \Generator
     *
     * @resolve bool
     */
    public function set(string $key, $value, int $expiration = 0): \Generator;

    /**
     * @coroutine
     *
     * @param string $key
     * @param mixed $value
     * @param int $expiration
     *
     * @return \Generator
     *
     * @resolve bool True if the value was added to the cache, false if the key was already in the cache.
     */
    public function add(string $key, $value, int $expiration = 0): \Generator;
    
    /**
     * @coroutine
     *
     * @param $key
     * @param $value
     * @param int $expiration
     *
     * @return \Generator
     * 
     * @resolve bool True if the value was replaced, false if the key was not in the cache.
     */
    public function replace(string $key, $value, int $expiration = 0): \Generator;

    /**
     * @coroutine
     *
     * @param string $key
     *
     * @return \Generator
     *
     * @resolve bool
     */
    public function delete(string $key): \Generator;

    /**
     * @coroutine
     *
     * @param string $key
     * @param callable $callback
     *
     * @return \Generator
     *
     * @resolve mixed Return/resolution value of $callback.
     */
    public function update(string $key, callable $callback, int $expiration = 0): \Generator;
}
