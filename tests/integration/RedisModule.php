<?php

namespace Kodus\PredisSimpleCache\Test;

use Codeception\Module\Redis as CodeceptionRedisModule;

/**
 * Extends the codeception redis module with a method for fetching the Predis Client.
 *
 * @see https://codeception.com/docs/modules/Redis
 */
class RedisModule extends CodeceptionRedisModule
{
    public function getClient(): \Predis\Client // FQN necessary to get correct typehints in auto-generated actor.
    {
        return $this->driver;
    }
}
