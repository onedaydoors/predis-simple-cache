<?php

namespace Kodus\PredisSimpleCache;

use DateInterval;
use Predis\Client;
use Psr\SimpleCache\CacheInterface;
use Throwable;
use Traversable;

/**
 * Bridges PSR-16 interface to a redis database via the predis/predis client.
 */
class PredisSimpleCache implements CacheInterface
{
    const PSR16_RESERVED = '/\{|\}|\(|\)|\/|\\\\|\@|\:/u';

    private Client $client;

    private int $default_ttl;

    /**
     * @param Client $client      The Predis Client to use for the cached data
     * @param int    $default_ttl Default time-to-live as a unix timestamp
     */
    public function __construct(Client $client, int $default_ttl)
    {
        $this->client = $client;
        $this->default_ttl = $default_ttl;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $this->validateKey($key);

        return $this->client->exists($key) ? unserialize($this->client->get($key)) : $default;
    }

    public function set(string $key, mixed $value, null|int|\DateInterval $ttl = null): bool
    {
        $this->validateKey($key);

        if (is_int($ttl)) {
            $expires = $ttl;
        } elseif ($ttl instanceof DateInterval) {
            $expires = $this->unixTimestampFromDateInterval($ttl) - time();
        } elseif ($ttl === null) {
            $expires = $this->default_ttl;
        } else {
            throw new InvalidArgumentException("Invalid TTL value");
        }

        if ($expires > 0) {
            return mb_strpos('OK', $this->client->setex($key, $expires, serialize($value))) !== false;
        } else {
            return $this->delete($key);
        }
    }

    public function delete(string $key): bool
    {
        $this->validateKey($key);

        $this->client->del($key);

        return true;
    }

    public function clear(): bool
    {
        $result = $this->client->flushall();

        return mb_strpos('OK', $result) !== false;
    }

    public function getMultiple(iterable $keys, mixed $default = null): iterable
    {
        $this->validateIterable($keys);

        /** @var Traversable|array $keys */
        $key_list = is_array($keys) ? $keys : iterator_to_array($keys);

        $result = [];
        foreach ($key_list as $key) {
            if (! is_int($key)) {
                $this->validateKey($key);
            }

            $value = $this->client->get($key);
            $result[$key] = $value ? unserialize($value) : $default;
        }

        return $result;
    }

    public function setMultiple(iterable $values, null|int|\DateInterval $ttl = null): bool
    {
        $this->validateIterable($values);

        //Dev. Note: Using mset does not allow TTL arguments, so it seems going through all keys is unavoidable.
        $this->client->multi();
        try {
            foreach ($values as $key => $value) {
                if (! is_int($key)) {
                    $this->validateKey($key);
                }
                $this->set((string) $key, $value, $ttl);
            }
        } catch (InvalidArgumentException $exception) {
            $this->client->discard();

            throw $exception;
        } catch (Throwable $throwable) {
            $this->client->discard();

            return false;
        }
        $this->client->exec();

        return true;
    }

    public function deleteMultiple(iterable $keys): bool
    {
        /** @var $keys array|Traversable */
        $this->validateIterable($keys);

        $arguments = []; // $keys might be a Traversable instance, so we map them to $arguments, while validating
        foreach ($keys as $key) {
            if (! is_int($key)) {
                $this->validateKey($key);
            }

            $arguments[] = $key;
        }

        if (count($arguments) < 1) {
            return true;
        }

        $this->client->del($arguments);

        return true;
    }

    public function has(string $key): bool
    {
        $this->validateKey($key);

        return $this->client->exists($key) === 1;
    }

    private function unixTimestampFromDateInterval(DateInterval $interval): int
    {
        $date_time = date_create_from_format('U', (string) time());
        $date_time->add($interval);

        return $date_time->getTimestamp();
    }

    /**
     * @param mixed $key
     *
     * @throws InvalidArgumentException If the key is not a valid key string.
     */
    private function validateKey($key): void
    {
        if (! is_string($key)) {
            $type = is_object($key) ? get_class($key) : gettype($key);

            throw new InvalidArgumentException("Invalid key type {$type}");
        }

        if ($key === "") {
            throw new InvalidArgumentException("Key must contain at least 1 character");
        }

        if (preg_match(self::PSR16_RESERVED, $key, $matches) === 1) {
            throw new InvalidArgumentException("Illegal character in key '{$matches[0]}'");
        }
    }

    /**
     * PSR-16 defines "iterable" as either an actual array or an instance of Traversable
     *
     * @param $values
     *
     * @throws InvalidArgumentException if the parameter is not an iterable value
     */
    private function validateIterable($values): void
    {
        if (! is_array($values) && ! $values instanceof Traversable) {
            throw new InvalidArgumentException("Values must be an array or instance of \Traversable");
        }
    }
}
