<?php

namespace Encore\Admin;

use Encore\Admin\Layout\Content;
use Illuminate\Routing\Router;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;

class AdminServiceProvider extends ServiceProvider
{
    /**
     * @var array
     */
    protected $commands = [
        Console\AdminCommand::class,
        Console\MakeCommand::class,
        Console\ControllerCommand::class,
        Console\MenuCommand::class,
        Console\InstallCommand::class,
        Console\PublishCommand::class,
        Console\UninstallCommand::class,
        Console\ImportCommand::class,
        Console\CreateUserCommand::class,
        Console\ResetPasswordCommand::class,
        Console\ExtendCommand::class,
        Console\ExportSeedCommand::class,
        Console\MinifyCommand::class,
        Console\FormCommand::class,
        Console\PermissionCommand::class,
        Console\ActionCommand::class,
        Console\GenerateMenuCommand::class,
        Console\ConfigCommand::class,
    ];

    /**
     * The application's route middleware.
     *
     * @var array
     */
    protected $routeMiddleware = [
        'admin.auth'       => Middleware\Authenticate::class,
        'admin.pjax'       => Middleware\Pjax::class,
        'admin.log'        => Middleware\LogOperation::class,
        'admin.permission' => Middleware\Permission::class,
        'admin.bootstrap'  => Middleware\Bootstrap::class,
        'admin.session'    => Middleware\Session::class,
    ];

    /**
     * The application's route middleware groups.
     *
     * @var array
     */
    protected $middlewareGroups = [
        'admin' => [
            'admin.auth',
            'admin.pjax',
            'admin.log',
            'admin.bootstrap',
            'admin.permission',
            //            'admin.session',
        ],
    ];

    /**
     * Boot the service provider.
     *
     * @return void
     */
    public function boot()
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'admin');

        $this->ensureHttps();

        if (file_exists($routes = admin_path('routes.php'))) {
            $this->loadRoutesFrom($routes);
        }

        $this->registerPublishing();

        $this->compatibleBlade();

        Blade::directive('box', function ($title) {
            return "<?php \$box = new \Encore\Admin\Widgets\Box({$title}, '";
        });

        Blade::directive('endbox', function ($expression) {
            return "'); echo \$box->render(); ?>";
        });

        Blade::directive('admin_assets', function ($name) {
            return "<?php echo \Encore\Admin\Admin::renderAssets({$name}); ?>";
        });
    }

    /**
     * Force to set https scheme if https enabled.
     *
     * @return void
     */
    protected function ensureHttps()
    {
        if (config('admin.https') || config('admin.secure')) {
            url()->forceScheme('https');
            $this->app['request']->server->set('HTTPS', true);
        }
    }

    /**
     * Register the package's publishable resources.
     *
     * @return void
     */
    protected function registerPublishing()
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([__DIR__.'/../config' => config_path()], 'laravel-admin-config');
            $this->publishes([__DIR__.'/../resources/lang' => resource_path('lang')], 'laravel-admin-lang');
            $this->publishes([__DIR__.'/../database/migrations' => database_path('migrations')], 'laravel-admin-migrations');
            $this->publishes([__DIR__.'/../resources/assets' => public_path('vendor/laravel-admin')], 'laravel-admin-assets');
        }
    }

    /**
     * Remove default feature of double encoding enable in laravel 5.6 or later.
     *
     * @return void
     */
    protected function compatibleBlade()
    {
        $reflectionClass = new \ReflectionClass('\Illuminate\View\Compilers\BladeCompiler');

        if ($reflectionClass->hasMethod('withoutDoubleEncoding')) {
            Blade::withoutDoubleEncoding();
        }
    }

    /**
     * Extends laravel router.
     */
    protected function macroRouter()
    {
        Router::macro('content', function ($uri, $content, $options = []) {
            return $this->match(['GET', 'HEAD'], $uri, function (Content $layout) use ($content, $options) {
                return $layout
                    ->title(Arr::get($options, 'title', ' '))
                    ->description(Arr::get($options, 'desc', ' '))
                    ->body($content);
            });
        });

        Router::macro('adminView', function ($uri, $component, $data = [], $options = []) {
            return $this->match(['GET', 'HEAD'], $uri, function (Content $layout) use ($component, $data, $options) {
                return $layout
                    ->title(Arr::get($options, 'title', ' '))
                    ->description(Arr::get($options, 'desc', ' '))
                    ->component($component, $data);
            });
        });
    }

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $this->loadAdminAuthConfig();

        $this->registerRouteMiddleware();

        $this->commands($this->commands);

        $this->macroRouter();
    }

    /**
     * Setup auth configuration.
     *
     * @return void
     */
    protected function loadAdminAuthConfig()
    {
        config(Arr::dot(config('admin.auth', []), 'auth.'));
    }

    /**
     * Register the route middleware.
     *
     * @return void
     */
    protected function registerRouteMiddleware()
    {
        // register route middleware.
        foreach ($this->routeMiddleware as $key => $middleware) {
            app('router')->aliasMiddleware($key, $middleware);
        }

        // register middleware group.
        foreach ($this->middlewareGroups as $key => $middleware) {
            app('router')->middlewareGroup($key, $middleware);
        }
    }
}
