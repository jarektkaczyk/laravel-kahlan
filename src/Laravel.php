<?php

namespace Sofa\LaravelKahlan;

use PHPUnit\Framework\TestCase;
use Illuminate\Foundation\Testing\Concerns;

/**
 * This class is a wrapper for the Laravel's built-in testing features.
 */
class Laravel extends TestCase
{
    use Concerns\InteractsWithContainer,
        Concerns\MakesHttpRequests,
        Concerns\InteractsWithAuthentication,
        Concerns\InteractsWithConsole,
        Concerns\InteractsWithDatabase,
        Concerns\InteractsWithSession,
        Concerns\MocksApplicationServices;

    protected $afterEachCallbacks = [];

    public function __call($method, $params)
    {
        return method_exists($this, $method)
                ? call_user_func_array([$this, $method], $params)
                : call_user_func_array([$this->app, $method], $params);
    }

    /**
     * Make everything public because we access it from the outside.
     *
     * @param string $property
     * @param mixed  $value
     */
    public function __set($property, $value)
    {
        $this->{$property} = $value;
    }

    /**
     * Make everything public because we access it from the outside.
     *
     * @param  string $property
     * @return mixed
     */
    public function __get($property)
    {
        return property_exists($this, $property) ? $this->{$property} : null;
    }

    /**
     * Laravel compatibility.
     *
     * For your own callbacks it is recommended to use kahlan before/after callbacks.
     *
     * @param  callable $callback
     * @return void
     */
    protected function beforeApplicationDestroyed(callable $callback)
    {
        $this->afterEachCallbacks[] = $callback;
    }

    /**
     * Call the laravel callbacks.
     *
     * @return void
     * @throws \Exception
     */
    public function afterEach()
    {
        foreach ($this->afterEachCallbacks as $callback) {
            call_user_func($callback);
        }
    }
}
