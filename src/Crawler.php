<?php

namespace Sofa\LaravelKahlan;

use PHPUnit_Framework_TestCase;
use Illuminate\Foundation\Testing\CrawlerTrait;
use Illuminate\Foundation\Testing\AssertionsTrait;
use Illuminate\Foundation\Testing\ApplicationTrait;

/**
 * This class is a wrapper for the Laravel's built-in testing features.
 */
class Crawler extends PHPUnit_Framework_TestCase
{
    use ApplicationTrait, AssertionsTrait, CrawlerTrait;

    public function __call($method, $params)
    {
        return method_exists($this, $method)
                ? call_user_func_array([$this, $method], $params)
                : call_user_func_array([$this->app, $method], $params);
    }

    public function __set($property, $value)
    {
        $this->{$property} = $value;
    }

    public function __get($property)
    {
        return property_exists($this, $property) ? $this->{$property} : null;
    }
}
