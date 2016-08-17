<?php

namespace Sofa\LaravelKahlan;

use Dotenv;
use Kahlan\Given;
use Kahlan\Suite;
use Kahlan\Cli\Kahlan;
use Kahlan\Filter\Filter;
use Illuminate\Support\Str;
use Illuminate\Foundation\Testing\CrawlerTrait;
use Illuminate\Foundation\Testing\AssertionsTrait;

class Env
{
    private static $base_path;
    private static $instance;

    /*
    |--------------------------------------------------------------------------
    | Wrappers that provide functionality analogical to Laravel testing traits.
    |--------------------------------------------------------------------------
    */
    const DATABASE_TRANSACTIONS = 'DatabaseTransactions';
    const DATABASE_MIGRATIONS   = 'DatabaseMigrations';
    const WITHOUT_MIDDLEWARE    = 'WithoutMiddlewares';
    const WITHOUT_EVENTS        = 'WithoutEvents';

    public static function bootstrap(Kahlan $kahlan, $base_path = null)
    {
        self::$base_path = $base_path ?: realpath(__DIR__.'/../../../../');

        /*
        |--------------------------------------------------------------------------
        | Prepare environment variables
        |--------------------------------------------------------------------------
        */
        $env = self::$instance = new self;
        $args = $kahlan->args();
        $args->argument('env', ['array' => true]);
        $args->argument('no-laravel', ['type' => 'boolean']);

        Filter::register('laravel.env', function ($chain) use ($args, $env) {
            $env->loadEnvFromFile('.env.kahlan');
            $env->loadEnvFromCli($args);
            return $chain->next();
        });

        /*
        |--------------------------------------------------------------------------
        | Create Laravel context for specs
        |--------------------------------------------------------------------------
        */
        Filter::register('laravel.start', function ($chain) use ($args, $env, $kahlan) {
            // Due to the fact that Laravel is refreshed for each single spec,
            // it has huge impact on performance, that's why we will allow
            // disabling laravel at runtime for specs not relying on it.
            if ($args->exists('no-laravel') && !$args->get('no-laravel')
                || !$args->exists('no-laravel') && !env('NO_LARAVEL')
            ) {
                $kahlan->suite()->before($env->startApplication());
                $kahlan->suite()->beforeEach($env->refreshApplication());
            }
            return $chain->next();
        });

        require __DIR__.'/helpers.php';

        /*
        |--------------------------------------------------------------------------
        | Apply customizations
        |--------------------------------------------------------------------------
        */
        Filter::apply($kahlan, 'interceptor', 'laravel.env');
        Filter::apply($kahlan, 'interceptor', 'laravel.start');
    }

    public function bootstrapLaravel()
    {
        $app = require self::$base_path.'/bootstrap/app.php';
        $app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();
        return $app;
    }

    public function startApplication()
    {
        return function () {
            $app = $this->bootstrapLaravel();

            given('app', function () use ($app) {
                return $app;
            });

            given('crawler', function () use ($app) {
                $crawler = new Crawler;
                $crawler->baseUrl = env('BASE_URL', 'localhost');
                $crawler->app = $app;
                return $crawler;
            });

            given('laravel', function () {
                return $this->crawler;
            });
        };
    }

    public function refreshApplication()
    {
        return function () {
            $app = $this->bootstrapLaravel();

            given('app', function () use ($app) {
                return $app;
            });
        };
    }

    /**
     * Apply before and/or after each spec callbacks.
     * Replacement for Laravel's testing traits, eg. `use DatabaseTransactions`
     *
     * @param  string|array $wrappers
     * @param  \Closure $closure
     * @return \Kahlan\Suite
     */
    public static function wrap($wrappers, $closure)
    {
        $befores = $afters = [];

        $wrappers = (array) $wrappers;

        foreach ($wrappers as $wrapper) {
            list($before, $after) = self::$instance->functionsFor($wrapper);
            if ($before) $befores[] = $before;
            if ($after) $afters[] = $after;
        }

        $context = Suite::current()->context('Following specs run using: ' . implode(', ', $wrappers) . ' ⤵', $closure);

        foreach ($befores as $callback) {
            $context->beforeEach($callback);
        }

        foreach (array_reverse($afters) as $callback) {
            $context->afterEach($callback);
        }

        return $context;
    }

    protected function functionsFor($wrapper)
    {
        // We're gonna mutate provided wrapper in order to make it flexible
        // on the developer's part, but standardized in the code below.
        //
        // It allows developer to provide any of the following to use DatabaseTransactions:
        //  - 'database.transactions' // dot notation
        //  - 'DatabaseTransactions'  // original, laravel trait name
        //  - 'database transactions' // simple human-readable string
        //  - 'database transaction'  // any of the above in SINGULAR
        //
        switch (Str::plural(Str::studly(str_replace('.', ' ', $wrapper)))) {
            case self::DATABASE_TRANSACTIONS:
                return [
                    function () {app('db')->beginTransaction();},
                    function () {app('db')->rollBack();},
                ];

            case self::DATABASE_MIGRATIONS:
                return [
                    function () {app('Illuminate\Contracts\Console\Kernel')->call('migrate');},
                    function () {app('Illuminate\Contracts\Console\Kernel')->call('migrate:rollback');},
                ];

            case self::WITHOUT_MIDDLEWARE:
                return [
                    function () {Suite::current()->laravel->instance('middleware.disable', true);},
                    null
                ];

            case self::WITHOUT_EVENTS:
                // to be implemented

        }

        return [null, null];
    }

    /**
     * Load environment variables from kahlan-specific env file if provided.
     *
     * @param  string $filename
     * @return void
     */
    public function loadEnvFromFile($filename)
    {
        if (is_readable($filename) && is_file($filename)) {
            Dotenv::load(self::$base_path, '.env.kahlan');
        } else {
            putenv('BASE_URL=http://localhost');
        }
    }

    /**
     * Load environment variables provided in CLI at runtime.
     *
     * @param  \Kahlan\Cli\Args $args
     * @return void
     */
    public function loadEnvFromCli($args)
    {
        $env = ['APP_ENV' => 'testing'];

        foreach ($args->get('env') as $key => $val) {
            foreach (explode(',', $val) as $arg) {
                list($k, $v) = explode(':', $arg);
                $env[$k] = $v;
            }
        }

        foreach ($env as $key => $val) {
            putenv($key.'='.$val);
        }
    }
}
