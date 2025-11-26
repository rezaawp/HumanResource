<?php

declare(strict_types=1);

namespace App\Extensions\HumanResource\System;

use App\Domains\Marketplace\Contracts\UninstallExtensionServiceProviderInterface;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use App\Extensions\HumanResource\System\Http\Controllers\HumanResourceController;

class HumanResourceProvider extends ServiceProvider implements UninstallExtensionServiceProviderInterface
{
    public function register(): void
    {
        $this->registerConfig();
    }

    public function boot(Kernel $kernel): void
    {
        $this->registerTranslations()
            ->registerViews()
            ->registerRoutes()
            ->registerMigrations()
            ->publishAssets()
            ->registerComponents();

    }

    public function registerComponents(): static
    {
        return $this;
    }

    public function publishAssets(): static
    {
        $this->publishes([
            __DIR__ . '/../resources/assets' => public_path('vendor/human-resource'),
        ], 'extension');

        return $this;
    }

    public function registerConfig(): static
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/human-resource.php', 'human-resource');

        return $this;
    }

    protected function registerTranslations(): static
    {
        $this->loadTranslationsFrom(__DIR__ . '/../resources/lang', 'human-resource');

        return $this;
    }

    public function registerViews(): static
    {
        $this->loadViewsFrom([__DIR__ . '/../resources/views'], 'human-resource');

        return $this;
    }

    public function registerMigrations(): static
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        return $this;
    }

    private function registerRoutes(): static
    {
        $this->router()
            ->group([
                'prefix' => 'human-resource',
                'middleware' => ['web', 'auth', 'rbac.permission'],
            ], function (Router $router) {
                $router->get("/welcome", [HumanResourceController::class, 'index'])->name('human-resource.index');

                $router->group([
                    'prefix' => 'user-management',
                ], function (Router $router) {
                    $router->name('dashboard.admin.hr.users.')->group(function (Router $router) {
                        $router->get('/', [\App\Extensions\HumanResource\System\Http\Controllers\UserManagementController::class, 'index'])->name('index');
                        $router->get('/{user}/edit', [\App\Extensions\HumanResource\System\Http\Controllers\UserManagementController::class, 'edit'])->name('edit');
                        $router->post('/save', [\App\Extensions\HumanResource\System\Http\Controllers\UserManagementController::class, 'usersSave'])->name('save');
                    });
                });
            });

        return $this;
    }

    private function router(): Router|Route
    {
        return $this->app['router'];
    }

    public static function uninstall(): void
    {
        // TODO: Implement uninstall() method.
    }
}
