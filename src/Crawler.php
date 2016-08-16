<?php

namespace Sofa\LaravelKahlan;

use PHPUnit_Framework_TestCase;
use Illuminate\Foundation\Testing\CrawlerTrait;
use Illuminate\Foundation\Testing\AssertionsTrait;

/**
 * This class is a wrapper for the Laravel's built-in testing features.
 */
class Crawler extends PHPUnit_Framework_TestCase
{
    use CrawlerTrait, AssertionsTrait;

    public function __call($method, $params)
    {
        return call_user_func_array([$this, $method], $params);
    }
}
