# Sofa/laravel-kahlan

[Kahlan](https://kahlan.readthedocs.io) suite for testing Laravel application providing intuitive kahlan (jasmine based) describe-it syntax with Laravel functional testing goodies.

See usage example on https://github.com/jarektkaczyk/kahlan-driven-laravel

## Why I should use the package & how it works?

Take a look at [the example spec](https://github.com/jarektkaczyk/kahlan-driven-laravel/blob/5.1/spec/AppSpec.php)

## First use in 3 steps

1. Add to your project
    ```
    composer require --dev sofa/laravel-kahlan:"~5.3"
    ```

2. Add this line to your kahlan config file (create it if necessary):
    ```php
    /*  /path/to/your/app/kahlan-config.php  */
    <?php

    Sofa\LaravelKahlan\Env::bootstrap($this);

    ```
     
3. Create your first spec in `/spec` folder, for example `/spec/AppSpec.php` and run test suite with `vendor/bin/kahlan`. Working example can be found on https://github.com/jarektkaczyk/kahlan-driven-laravel
    ```php
    /*  /path/to/your/app/spec/AppSpec.php  */
    <?php

    describe('My awesome Kahlan driven Laravel app', function () {
        it("provides the same testing API as Laravel's own TestCase", function () {
            $this->laravel->get('/')
                          ->see('Laravel 5')
                          ->assertResponseOk();
        });
    }

    ```

---

### Optional stuff

* Should you need to customize **.env** variables for the test suite, you have 2 options:
    - In the `.env.kahlan` file for persistent variables
    - At runtime:

        ```
        /path/to/app$ vendor/bin/kahlan -env=DB_CONNECTION=sqlite,MAIL_DRIVER=log
        ```

* In your specs you can use all the kahlan features, as well as Laravel testing sugar:
    - **helpers**: `app()`, `event()` etc
    - Application methods `$this->app->method()` or `$this->laravel->method()`
    - Laravel **TestCase features**, eg. `$this->laravel->get('/')->assertResponseOk()`
    - **Application instance** as either of: `$this->app === $this->laravel->app === app()`

* For tests that *don't require* Laravel there's `--no-laravel` cli option, since booting up the application for each test has huge impact on performance: 
    ```
    /path/to/app$ vendor/bin/kahlan --spec=spec/unit --no-laravel
    ```

    Alternatively you can provide `NO_LARAVEL=true` in `.env`/`.env.kahlan` file, then you would enable laravel only when necessary:
    ```
    /path/to/app$ vendor/bin/kahlan --spec=spec/functional --no-laravel=false
    ```

---

#Happy coding!
