<?php

namespace Sofa\LaravelKahlan;

use Kahlan\Suite;
use Dotenv\Dotenv;
use Kahlan\Cli\Kahlan;
use Kahlan\Plugin\Stub;
use Kahlan\Filter\Filter;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * This class provides Laravel environment for the Kahlan BDD test suite.
 *
 * USAGE:
 *
 * 1. Create `kahlan-config.php` file in your app root folder (if not exists already)
 * 2. Add `Sofa\LaravelKahlan\Env::bootstrap($this);` to kahlan-config.php
 * 3. Create your first spec in /spec folder, eg. /spec/AppSpec.php
 *    Example spec can be found here:
 *    @link https://github.com/jarektkaczyk/kahlan-driven-laravel
 *
 * 4. Should you need to customize .env variables for the test suite, you can do it:
 *     4.1. In the `.env.kahlan` file for persistent env variables
 *     4.2. At runtime: `/app_path/$ vendor/bin/kahlan -env=DB_CONNECTION=sqlite,MAIL_DRIVER=log`
 *
 * 5. You can use all of the kahlan features in your specs as well as the Laravel sugar:
 *     5.1. All the helpers: app(), event() etc
 *     5.2. Application methods `$this->app->method()` or `$this->laravel->method()`
 *     5.4. Laravel TestCase features as `$this->laravel->get('/')->assertResponseOk()`
 *     5.5. Application instance as either of: `$this->app === $this->laravel->app === app()`
 *
 *
 * Happy coding!
 */
class Env
{
    /** @var string Application root folder */
    private static $base_path;

    /** @var \Sofa\LaravelKahlan\Env Singleton instance */
    private static $instance;

    /*
    |--------------------------------------------------------------------------
    | Wrappers that provide functionality of the Laravel testing traits.
    |--------------------------------------------------------------------------
    */
    const DATABASE_TRANSACTIONS = 'DatabaseTransactions';
    const DATABASE_MIGRATIONS   = 'DatabaseMigrations';
    const WITHOUT_MIDDLEWARE    = 'WithoutMiddlewares';
    const WITHOUT_EVENTS        = 'WithoutEvents';

    /**
     * Start the engine and get the wheels turning.
     *
     * @param  \Kahlan\Cli\Kahlan $kahlan
     * @param  string $base_path
     * @return void
     */
    public static function bootstrap(Kahlan $kahlan, $base_path = null)
    {
        self::$base_path = $base_path ?: realpath(__DIR__.'/../../../../');

        /*
        |--------------------------------------------------------------------------
        | Prepare environment variables
        |--------------------------------------------------------------------------
        */

        $env = self::$instance = new self;
        $commandLine = $kahlan->commandLine();

        $commandLine->option('env', ['array' => true]);
        $commandLine->option('no-laravel', ['type' => 'boolean']);

        Filter::register('laravel.env', function ($chain) use ($commandLine, $env) {
            $env->loadEnvFromFile('.env.kahlan');
            $env->loadEnvFromCli($commandLine);

            return $chain->next();
        });

        /*
        |--------------------------------------------------------------------------
        | Create Laravel context for specs
        |--------------------------------------------------------------------------
        */
        Filter::register('laravel.start', function ($chain) use ($commandLine, $env, $kahlan) {
            // Due to the fact that Laravel is refreshed for each single spec,
            // it has huge impact on performance, that's why we will allow
            // disabling laravel at runtime for specs not relying on it.
            if ($commandLine->exists('no-laravel') && !$commandLine->get('no-laravel')
                || !$commandLine->exists('no-laravel') && !env('NO_LARAVEL')
            ) {
                $kahlan->suite()->beforeAll($env->refreshApplication());
                $kahlan->suite()->beforeEach($env->refreshApplication());
                $kahlan->suite()->afterEach($env->beforeLaravelDestroyed());
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

    /**
     * Provide fresh application instance for each single spec.
     *
     * @return \Closure
     */
    public function refreshApplication()
    {
        return function () {
            $laravel = new Laravel;
            $laravel->baseUrl = env('BASE_URL', 'localhost');
            $laravel->app = $this->bootstrapLaravel();

            $context = Suite::current();
            $context->app = $laravel->app;
            $context->laravel = $laravel;
        };
    }

    /**
     * Bootstrap laravel application.
     *
     * @return \Illuminate\Foundation\Application
     */
    protected function bootstrapLaravel()
    {
        $app = require self::$base_path.'/bootstrap/app.php';
        $app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();
        return $app;
    }

    /**
     * Run Laravel-specific callbacks after each spec.
     *
     * @return void
     */
    public function beforeLaravelDestroyed()
    {
        return function () {
            Suite::current()->laravel->afterEach();
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
    public static function wrap($wrappers, $closure, $mode = 'normal')
    {
        $befores = $afters = [];

        $wrappers = (array) $wrappers;

        foreach ($wrappers as $wrapper) {
            list($before, $after) = self::$instance->functionsFor($wrapper);
            if ($before) $befores[] = $before;
            if ($after) $afters[] = $after;
        }

        $message = 'Following specs run using: ' . implode(', ', $wrappers) . ' ⤵';
        $context = Suite::current()->context($message, $closure, null, $mode);

        foreach ($befores as $callback) {
            $context->beforeEach($callback);
        }

        foreach (array_reverse($afters) as $callback) {
            $context->afterEach($callback);
        }

        return $context;
    }

    /**
     * Get before & after callbacks for given wrapper.
     *
     * @param  string $wrapper
     * @return array
     * @throws \InvalidArgumentException
     */
    protected function functionsFor($wrapper)
    {
        /*
        |--------------------------------------------------------------------------
        | We're gonna mutate provided wrapper in order to make it flexible
        | on the developer's part, but standardized in the code below.
        |
        | It allows developer to provide any of the following to use DatabaseTransactions:
        |  - 'database.transactions' // dot notation
        |  - 'DatabaseTransactions'  // original, laravel trait name
        |  - 'database transactions' // simple human-readable string
        |  - 'database transaction'  // any of the above in SINGULAR
        |--------------------------------------------------------------------------
        */
        switch (Str::plural(Str::studly(str_replace('.', ' ', $wrapper)))) {
            case self::DATABASE_TRANSACTIONS:
                $before = function () {Suite::current()->laravel->make('db')->beginTransaction();};
                $after = function () {Suite::current()->laravel->make('db')->rollBack();};
                break;

            case self::DATABASE_MIGRATIONS:
                $before = function () {Suite::current()->laravel->artisan('migrate');};
                $after = function () {Suite::current()->laravel->artisan('migrate:rollback');};
                break;

            case self::WITHOUT_MIDDLEWARE:
                $before = function () {Suite::current()->laravel->instance('middleware.disable', true);};
                $after = null;
                break;

            case self::WITHOUT_EVENTS:
                $before = function () {
                            $mock = Stub::create(['implements' => ['Illuminate\Contracts\Events\Dispatcher']]);
                            Suite::current()->laravel->app->instance('events', $mock);
                        };
                $after = null;
                break;

            default:
                throw new InvalidArgumentException(sprintf('Unknown wrapper [%s]', $wrapper));
        }

        return [$before, $after];
    }

    /**
     * Load environment variables from kahlan-specific env file if provided.
     *
     * @param  string $filename
     * @return void
     */
    public function loadEnvFromFile($filename)
    {
        putenv('BASE_URL=http://localhost');

        if (is_readable($filename) && is_file($filename)) {
            (new Dotenv(self::$base_path, $filename))->load();
        }
    }

    /**
     * Load environment variables provided in CLI at runtime.
     *
     * @param  \Kahlan\Cli\CommandLine $commandLine
     * @return void
     */
    public function loadEnvFromCli($commandLine)
    {
        $env = ['APP_ENV' => 'testing'];

        foreach ($commandLine->get('env') as $key => $val) {
            foreach (explode(',', $val) as $arg) {
                list($k, $v) = preg_split('/:|=/', $arg);
                $env[$k] = $v;
            }
        }

        foreach ($env as $key => $val) {
            putenv($key.'='.$val);
        }
    }
}
