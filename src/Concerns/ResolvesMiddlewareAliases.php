<?php

namespace SajjadHossain\Doctor\Concerns;

trait ResolvesMiddlewareAliases
{
    private function getRegisteredAliases(): array
    {
        $aliases = [];

        $kernel = app(\Illuminate\Contracts\Http\Kernel::class);
        if (method_exists($kernel, 'getRouteMiddleware')) {
            $aliases = array_merge($aliases, array_keys($kernel->getRouteMiddleware()));
        }

        $router = app('router');
        if (method_exists($router, 'getMiddleware')) {
            $aliases = array_merge($aliases, array_keys($router->getMiddleware()));
        }

        return array_values(array_unique($aliases));
    }

    private function getRegisteredGroups(): array
    {
        $groups = [];

        $kernel = app(\Illuminate\Contracts\Http\Kernel::class);
        if (method_exists($kernel, 'getMiddlewareGroups')) {
            $groups = array_merge($groups, array_keys($kernel->getMiddlewareGroups()));
        }

        $router = app('router');
        if (method_exists($router, 'getMiddlewareGroups')) {
            $groups = array_merge($groups, array_keys($router->getMiddlewareGroups()));
        }

        return array_values(array_unique($groups));
    }
}
