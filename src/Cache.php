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
    public function exists($key);

    /**
     * @coroutine
     *
     * @param string $key
     *
     * @return \Generator
     *
     * @resolve mixed|null Null returned if the key did not exist.
     */
    public function get($key);

    /**
     * @coroutine
     *
     * Sets the key to the given value. If the $expiration param is non-zero, the value will be automatically deleted
     * after $expiration seconds.
     *
     * @param string $key
     * @param mixed $value
     * @param int $expiration
     *
     * @return \Generator
     *
     * @resolve bool
     */
    public function set($key, $value, $expiration = 0);

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
    public function add($key, $value, $expiration = 0);
    
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
    public function replace($key, $value, $expiration = 0);

    /**
     * @coroutine
     *
     * @param string $key
     *
     * @return \Generator
     *
     * @resolve bool
     */
    public function delete($key);

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
    public function update($key, callable $callback, $expiration = 0);
}
