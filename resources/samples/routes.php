<?php

use App\Http\Controller\TaskController;
use App\Http\Middleware\RequireApiKey;
use Crystoline\Xpsr\Routing\RouteCollection;

return static function (RouteCollection $routes): void {
    $routes->get('/tasks', [TaskController::class, 'index'])
        ->name('tasks.index');

    $routes->get('/tasks/{id}', [TaskController::class, 'show'])
        ->where('id', '\d+')
        ->name('tasks.show');

    $routes->post('/tasks', [TaskController::class, 'store'])
        ->middleware(RequireApiKey::class);
};
