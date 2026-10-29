# Portable routing

Routes are declared once. Start with [Getting started](GETTING_STARTED.md#create-a-project), then read about [views](VIEWS.md).

## Declare routes

The [cache package](../packages/cache/README.md) has [a registry](../packages/cache/src/CacheServiceRegistry.php#L10).
Plans are in [the plan](FRAMEWORK_PLAN.md), packages in [packages/](../packages/), standards at [PHP-FIG](https://www.php-fig.org/psr/).
Jump [back up](#declare-routes).

## `app.view_engine` and Twig's {{ }}

A [broken link](NOWHERE.md) and a [missing anchor](GETTING_STARTED.md#nope).

```php
$routes->get('/users/{id}', [UserController::class, 'show']);
```

| Adapter | Class |
|---|---|
| `fastroute` | `FastRouteRouter` |

## Declare routes

The same heading again.
