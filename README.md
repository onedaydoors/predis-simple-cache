kodus/predis-simple-cache
=========================

A lightweight bridge from [predis/predis](https://packagist.org/packages/predis/predis) to the 
[PSR-16 simple-cache interface](https://www.php-fig.org/psr/psr-16/)

## Installation

The library is distributed as a composer package.

```
composer require kodus/predis-simple-cache
```

## Usage

Bootstrapping the cache class is very simple. The `PredisSimpleCache` constructor requires the predis client to store
the cache items and a default TTL integer value.

In the example below the cache is constructed with a client with no custom settings and a default TTL of an hour.

```php
<?php
$client = new Predis\Client();
$cache = new Kodus\PredisSimpleCache\PredisSimpleCache($client, 60 * 60);
```

## Developer notes
The `Predis\ClientInterface` interface from `predis/predis` defines the API via `@method` docblock annotations entries
which are then invoked by the `__call()` method.

The typehints in these annotations have proven to be a bit unreliable, and in cases like 
`Predis\ClientInterface::setex()`, we've had to refer to the [Redis documentation](https://redis.io/commands/setex)
instead.
