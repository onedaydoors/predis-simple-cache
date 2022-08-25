<?php
declare(strict_types=1);

namespace Kodus\PredisSimpleCache\Test;

use Codeception\Example;
use DateInterval;
use Generator;
use IntegrationTester;
use Kodus\PredisSimpleCache\PredisSimpleCache;
use Psr\SimpleCache\CacheInterface;
use Psr\SimpleCache\InvalidArgumentException;
use TypeError;

class PredisSimpleCacheCest
{
    private const DEFAULT_TTL = 1;

    private PredisSimpleCache $cache;

    public function _before(IntegrationTester $I): void
    {
        $I->cleanup(); // Empties Redis database before each test

        $this->cache = new PredisSimpleCache($I->getClient(), self::DEFAULT_TTL);
    }

    public function instanceOfSimpleCache(IntegrationTester $I): void
    {
        $I->assertInstanceOf(CacheInterface::class, $this->cache, "The cache must implement the PSR-16 interface");
    }

    public function unknownValues(IntegrationTester $I): void
    {
        $I->assertNull($this->cache->get('key'), 'Returns null on unknown key');
        $I->assertFalse($this->cache->has('key'), 'Returns null on unknown key');
    }

    public function setSingleValues(IntegrationTester $I): void
    {
        $result1 = $this->cache->set('key1', 'value1');
        $result2 = $this->cache->set('key2', 'value2', 2);
        $result3 = $this->cache->set('key3', 'value3', new DateInterval('PT2S'));

        $I->assertTrue($result1, 'set(string, mixed) must return true if success');
        $I->assertTrue($result2, 'set(string, mixed, int) must return true if success');
        $I->assertTrue($result3, 'set(string, mixed, DateInterval) must return true if success');

        $I->assertTrue($this->cache->has('key1'), 'has() returns true for key #1');
        $I->assertTrue($this->cache->has('key2'), 'has() returns true for key #2');
        $I->assertTrue($this->cache->has('key3'), 'has() returns true for key #3');

        $I->assertSame('value1', $this->cache->get('key1'), 'Value #1 is returned correctly');
        $I->assertSame('value2', $this->cache->get('key2'), 'Value #2 is returned correctly');
        $I->assertSame('value3', $this->cache->get('key3'), 'Value #3 is returned correctly');
    }

    public function setExpiredTtl(IntegrationTester $I): void
    {
        $this->cache->set('key1', 'value');
        $this->cache->set('key1', 'value', 0);
        $this->cache->set('key2', 'value');
        $this->cache->set('key2', 'value', -1);

        $I->assertNull($this->cache->get('key1'), "0 TTL results in deleted value");
        $I->assertNull($this->cache->get('key2'), "negative TTL results in deleted value");

        $I->assertFalse($this->cache->has('key1'), "0 TTL results in deleted value");
        $I->assertFalse($this->cache->has('key2'), "negative TTL results in deleted value");
    }

    public function deleteValue(IntegrationTester $I): void
    {
        $this->cache->set('key1', 'value');

        $result1 = $this->cache->delete('key1');
        $result2 = $this->cache->delete('key2');

        $I->assertTrue($result1, 'Delete must return true on success');
        $I->assertTrue($result2, 'Deleting a value that does not exist should return true');

        $I->assertNull($this->cache->get('key'), 'Values must be deleted on delete()');
        $I->assertFalse($this->cache->has('key'), 'Values must be deleted on delete()');
    }

    public function clear(IntegrationTester $I): void
    {
        $result_empty = $this->cache->clear();
        $this->cache->set('key', 'value');
        $result_non_empty = $this->cache->clear();

        $I->assertTrue($result_empty, 'Clearing an empty cache should return true');
        $I->assertTrue($result_non_empty, 'Clearing a non-empty cache should return true');

        $I->assertNull($this->cache->get('key'), 'Values must be deleted on clear()');
        $I->assertFalse($this->cache->has('key'), 'Values must be deleted on clear()');
    }

    public function setMultiple(IntegrationTester $I): void
    {
        $result = $this->cache->setMultiple([
            '0'    => 'value0',
            'key1' => 'value1',
            'key2' => 'value2',
        ]);

        $I->assertTrue($result, 'setMultiple() must return true if success');
        $I->assertEquals('value0', $this->cache->get('0'), 'Integer keys work when setting multiple values');
        $I->assertEquals('value1', $this->cache->get('key1'), 'Value for key1 returned correctly');
        $I->assertEquals('value2', $this->cache->get('key2'), 'Value for key2 returned correctly');
    }

    public function setMultipleWithExpiredTTL(IntegrationTester $I): void
    {
        $this->cache->setMultiple([
            'key1' => 'value',
            'key2' => 'value',
        ], 0);

        $this->cache->setMultiple([
            'key3' => 'value',
            'key4' => 'value',
        ], -2);

        $I->assertNull($this->cache->get('key1'), "key1 is not present after zero ttl");
        $I->assertNull($this->cache->get('key2'), "key2 is not present after zero ttl");
        $I->assertNull($this->cache->get('key3'), "key3 is not present after negative ttl");
        $I->assertNull($this->cache->get('key4'), "key4 is not present after negative ttl");

        $I->assertFalse($this->cache->has('key1'), "key1 is not present after zero ttl");
        $I->assertFalse($this->cache->has('key2'), "key2 is not present after zero ttl");
        $I->assertFalse($this->cache->has('key3'), "key3 is not present after negative ttl");
        $I->assertFalse($this->cache->has('key4'), "key4 is not present after negative ttl");
    }

    public function getMultiple(IntegrationTester $I): void
    {
        $this->cache->set('key1', 'value');
        $this->cache->set('key2', 'value2');

        $expected = [
            'key1' => 'value',
            'key2' => 'value2',
            'key3' => null,
        ];
        $actual = $this->cache->getMultiple(array_keys($expected));

        $I->assertEqualsCanonicalizing($expected, $actual, 'Can fetch multiple');
    }

    public function getMultipleWithDefault(IntegrationTester $I): void
    {
        $this->cache->set('key1', 'value');
        $this->cache->set('key2', 'value2');

        $expected = [
            'key1' => 'value',
            'key2' => 'value2',
            'key3' => 'default value',
        ];
        $actual = $this->cache->getMultiple(array_keys($expected), 'default value');

        $I->assertEqualsCanonicalizing($expected, $actual, 'Can fetch multiple');
    }

    public function deleteMultiple(IntegrationTester $I): void
    {
        $this->cache->set('key1', 'value1');
        $this->cache->set('key2', 'value2');

        $I->assertTrue($this->cache->deleteMultiple([]), 'Deleting a empty array should return true');
        $I->assertTrue($this->cache->deleteMultiple(['key']),
            'Deleting a value that does not exist should return true');

        $I->assertTrue($this->cache->deleteMultiple(['key1', 'key2']), 'Delete must return true on success');

        $I->assertNull($this->cache->get('key1'), 'Values must be deleted on deleteMultiple()');
        $I->assertNull($this->cache->get('key2'), 'Values must be deleted on deleteMultiple()');
    }

    public function basicUsageWithLongKey(IntegrationTester $I): void
    {
        $key1 = str_repeat('a', 300); //Technically PSR-16 only requires support for 64 characters in keys.
        $key2 = str_repeat('b', 300);
        $key3 = str_repeat('c', 300);

        $this->cache->set($key1, 'value');

        $this->cache->set($key2, 'value');
        $this->cache->delete($key2);

        $I->assertTrue($this->cache->has($key1));
        $I->assertSame('value', $this->cache->get($key1), 'Can set and get with a very long key');

        $I->assertFalse($this->cache->has($key2), 'Can delete with very long key');
        $I->assertNull($this->cache->get($key2), 'Can delete with very long key');

        $I->assertFalse($this->cache->has($key3), 'Can check non-existing and very long key');
        $I->assertNull($this->cache->get($key3), 'Can check non-existing and very long key');
    }

    public function setMultipleWithGenerator(IntegrationTester $I): void
    {
        $generator = $this->createGenerator([
            'key1' => 'value1',
            'key2' => 'value2',
        ]);

        $this->cache->setMultiple($generator);

        $I->assertEquals('value1', $this->cache->get('key1'), 'Can get value set with generator');
        $I->assertEquals('value2', $this->cache->get('key2'), 'Can get value set with generator');
    }

    public function testGetMultipleWithGenerator(IntegrationTester $I): void
    {
        $this->cache->set('key1', 'value1');
        $generator = $this->createGenerator(['key1', 'key2']);

        $expected = [
            'key1' => 'value1',
            'key2' => null,
        ];

        $actual = $this->cache->getMultiple($generator);

        $I->assertEqualsCanonicalizing($expected, $actual, "Can getMultiple with a generator");
    }

    public function deleteMultipleWithGenerator(IntegrationTester $I): void
    {
        $generator = $this->createGenerator(['key1', 'key2']);
        $this->cache->set('key1', 'value');

        $result = $this->cache->deleteMultiple($generator);

        $I->assertTrue($result, 'Deleting a generator should return true');
        $I->assertFalse($this->cache->has('key1'), 'Values must be deleted on deleteMultiple()');
        $I->assertNull($this->cache->get('key1'), 'Values must be deleted on deleteMultiple()');
    }

    /**
     * @dataProvider invalidKeys
     *
     * @see          self::invalidKeys()
     */
    public function throwOnInvalidKeys(IntegrationTester $I, Example $example)
    {
        $key = $example['key'];

        $I->expectThrowable(InvalidArgumentException::class, fn() => $this->cache->get($key));
    }

    /**
     * @dataProvider invalidArrayKeys
     */
    public function testGetMultipleInvalidKeys(IntegrationTester $I, Example $example)
    {
        $keys = ['key1', $example['key'], 'key2'];

        $I->expectThrowable(InvalidArgumentException::class, fn() => $this->cache->getMultiple($keys));
    }

    public function getMultipleNoIterable(IntegrationTester $I): void
    {
        $I->expectThrowable(TypeError::class, fn() => $this->cache->getMultiple('key'));
    }

    /**
     * @dataProvider invalidKeys
     */
    public function setInvalidKeys(IntegrationTester $I, Example $example): void
    {
        $key = $example['key'];

        $I->expectThrowable(InvalidArgumentException::class, fn() => $this->cache->set($key, 'value'));
    }

    /**
     * @dataProvider invalidArrayKeys
     */
    public function setMultipleInvalidKeys(IntegrationTester $I, Example $example): void
    {
        $generator = (function () use ($example) {
            yield 'key1' => 'value1';
            yield $example['key'] => 'value';
            yield 'key2' => 'value2';
        })();

        $I->expectThrowable(InvalidArgumentException::class, fn() => $this->cache->setMultiple($generator));
    }

    public function setMultipleNoIterable(IntegrationTester $I): void
    {
        $I->expectThrowable(TypeError::class, fn() => $this->cache->setMultiple('key'));
    }

    /**
     * @dataProvider invalidKeys
     */
    public function hasWithInvalidKeys(IntegrationTester $I, Example $example): void
    {
        $key = $example['key'];
        $I->expectThrowable(InvalidArgumentException::class, fn() => $this->cache->has($key));
    }

    /**
     * @dataProvider invalidKeys
     */
    public function deleteInvalidKeys(IntegrationTester $I, Example $example): void
    {
        $key = $example['key'];
        $I->expectThrowable(InvalidArgumentException::class, fn() => $this->cache->delete($key));
    }

    /**
     * @dataProvider invalidArrayKeys
     */
    public function deleteMultipleInvalidKeys(IntegrationTester $I, Example $example): void
    {
        $key_list = [
            'key1',
            $example['key'],
            'key2',
        ];
        $I->expectThrowable(InvalidArgumentException::class, fn() => $this->cache->deleteMultiple($key_list));
    }

    public function deleteMultipleNoIterable(IntegrationTester $I): void
    {
        $I->expectThrowable(TypeError::class, fn() => $this->cache->deleteMultiple('key'));
    }

    /**
     * @dataProvider invalidTtl
     */
    public function setInvalidTtl(IntegrationTester $I, Example $example): void
    {
        $ttl = $example['ttl'];

        $I->expectThrowable(TypeError::class, fn() => $this->cache->set('key', 'value', $ttl));
    }

    /**
     * @dataProvider invalidTtl
     */
    public function setMultipleInvalidTtl(IntegrationTester $I, Example $example): void
    {
        $ttl = $example['ttl'];

        $I->expectThrowable(
            TypeError::class,
            fn() => $this->cache->setMultiple(['key' => 'value'], $ttl)
        );
    }

    public function nullOverwrite(IntegrationTester $I): void
    {
        $this->cache->set('key', 5);
        $this->cache->set('key', null);

        $I->assertNull($this->cache->get('key'), 'Setting null to a key must overwrite previous value');
    }

    public function dataTypeString(IntegrationTester $I): void
    {
        $this->cache->set('key', '5');
        $result = $this->cache->get('key');

        $I->assertSame('5', $result, 'String value should keep type in cache');
    }

    public function dataTypeInteger(IntegrationTester $I): void
    {
        $this->cache->set('key', 5);
        $result = $this->cache->get('key');

        $I->assertSame(5, $result, 'Integer value should keep type in cache');
    }

    public function dataTypeFloat(IntegrationTester $I): void
    {
        $float = 1.23456789;
        $this->cache->set('key', $float);

        $result = $this->cache->get('key');

        $I->assertSame($float, $result, 'String value should keep type in cache');
    }

    public function dataTypeBoolean(IntegrationTester $I): void
    {
        $this->cache->set('key', false);
        $result = $this->cache->get('key');

        $I->assertFalse($result);
        $I->assertTrue($this->cache->has('key'), 'has() should return true when true are stored.');
    }

    public function dataTypeArray(IntegrationTester $I): void
    {
        $array = ['a' => 'foo', 2 => 'bar'];
        $this->cache->set('key', $array);
        $result = $this->cache->get('key');

        $I->assertEquals($array, $result, 'Returns arrays correctly');
    }

    public function dataTypeObject(IntegrationTester $I): void
    {
        $object = new \stdClass();
        $object->a = 'foo';
        $this->cache->set('key', $object);
        $result = $this->cache->get('key');

        $I->assertEquals($object, $result, 'Serializes and unserializes simple objects');
    }

    public function binaryData(IntegrationTester $I): void
    {
        $data = '';
        for ($i = 0; $i < 256; $i++) {
            $data .= chr($i);
        }

        $this->cache->set('key', $data);
        $result = $this->cache->get('key');

        $I->assertSame($data, $result, 'Binary data must survive a round trip.');
    }

    /**
     * @dataProvider validKeys
     */
    public function setValidKeys(IntegrationTester $I, Example $example): void
    {
        $key = $example['key'];
        $this->cache->set($key, 'foobar');

        $I->assertEquals('foobar', $this->cache->get($key), "Can use valid key {$key}");
    }

    /**
     * @dataProvider validKeys
     */
    public function setMultipleValidKeys(IntegrationTester $I, Example $example): void
    {
        $key = $example['key'];
        $values = [$example['key'] => 'value'];

        $this->cache->setMultiple($values);

        $I->assertEquals($values, $this->cache->getMultiple([$key]), "Can use valid key {$key}");
    }

    /**
     * @dataProvider validData
     */
    public function setValidData(IntegrationTester $I, Example $example): void
    {
        $value = $example['value'];
        $this->cache->set('key', $value);

        $I->assertEquals($value, $this->cache->get('key'), "Can use valid data");
    }

    /**
     * @dataProvider validData
     */
    public function setMultipleValidData(IntegrationTester $I, Example $example): void
    {
        $value = $example['value'];
        $value_list = ['key' => $value];

        $this->cache->setMultiple($value_list);
        $result = $this->cache->getMultiple(['key']);

        $I->assertEquals($value_list, $result, "Can use valid data in setMultiple");
    }

    public function objectAsDefaultValue(IntegrationTester $I): void
    {
        $obj = new \stdClass();
        $obj->foo = 'value';
        $I->assertEquals($obj, $this->cache->get('key', $obj), 'Can use object as default');
    }

    public function objectDoesNotChangeInCache(IntegrationTester $I): void
    {
        $obj = new \stdClass();
        $obj->foo = 'value';
        $this->cache->set('key', $obj);
        $obj->foo = 'changed';

        $cached_object = $this->cache->get('key');
        $I->assertEquals('value', $cached_object->foo, 'Object in cache should not have their values changed.');
    }

    /**
     * Dev Note:
     *
     * tl;dr; Using sleep() instead of mock clock is necessary due to Redis' way of setting expirations.
     *
     * It would be ideal to "fake" expiration with a mocked clock or similar, but since this is controlled
     * by redis' expiration logic, we can't "advance time" in a mocked clock class / callback.
     *
     * If we "turned back time" in the mocked clock, then the ttl will become negative internally in the cache
     * bridge, and negative TTL values have special meaning on redis, so testing this way would lead to a succesfull
     * assertion, but the internal logic would not resemble production logic and the test would be without value.
     *
     * So instead we accept the brute force use of sleep() and try to minimize the number of times we have to
     * invoke this method during the test suite to not slow down the test suite too much.
     *
     * Also try to keep this function after other test cases, so the slowest test is done last.
     */
    public function valuesExpireAfterTTL(IntegrationTester $I): void
    {
        $this->cache->set('key1', 'value');
        $this->cache->set('key2', 'value', 3);
        $this->cache->set('key3', 'value', new DateInterval('PT3S'));

        $this->cache->setMultiple([
            'key4' => 'value',
            'key5' => 'value',
        ], 3);

        $this->cache->setMultiple([
            'key6' => 'value',
            'key7' => 'value',
        ], new DateInterval('PT3S'));

        sleep(2);
        $I->assertNull($this->cache->get('key1'), 'Default TTL (1s) is applied');
        $I->assertFalse($this->cache->has('key1'), 'Default TTL (1s) is applied');

        sleep(2);
        $I->assertNull($this->cache->get('key2'), 'Value for key2 must expire after ttl (3s)');
        $I->assertNull($this->cache->get('key3'), 'Value for key3 must expire after ttl (3s)');
        $I->assertNull($this->cache->get('key4'), 'Value for key4 must expire after ttl (3s)');
        $I->assertNull($this->cache->get('key5'), 'Value for key5 must expire after ttl (3s)');
        $I->assertNull($this->cache->get('key6'), 'Value for key6 must expire after ttl (3s)');
        $I->assertNull($this->cache->get('key7'), 'Value for key7 must expire after ttl (3s)');

        $I->assertFalse($this->cache->has('key2'), 'has() returns false for key2 after ttl');
        $I->assertFalse($this->cache->has('key3'), 'has() returns false for key3 after ttl');
        $I->assertFalse($this->cache->has('key4'), 'has() returns false for key4 after ttl');
        $I->assertFalse($this->cache->has('key5'), 'has() returns false for key5 after ttl');
        $I->assertFalse($this->cache->has('key6'), 'has() returns false for key6 after ttl');
        $I->assertFalse($this->cache->has('key7'), 'has() returns false for key7 after ttl');
    }

    /**
     * PSR-16 only requires support for a-zA-Z0-9.- but Redis handles other characters very well, so an edge case
     * beyond a-zA-Z0-9.- is added to assert and document this.
     *
     * @return string[][]
     */
    protected function validKeys(): array
    {
        return [
            ['key' => 'AbC19-.'],
            ['key' => '1234567890123456789012345678901234567890123456789012345678901234'],
            ['key' => '!"#¤%&£$=→ß“ªĸ↓ð_'],
        ];
    }

    protected function validData(): array
    {
        return [
            ['value' => 'AbC19-.'],
            ['value' => 4711],
            ['value' => 47.11],
            ['value' => true],
            ['value' => null],
            ['value' => ['key' => 'value']],
            ['value' => new \stdClass()],
        ];
    }

    /**
     * ['0' => 'some data'] and [0 => 'some data'] are equivalent in PHP, so we have to accept integers as keys, when
     * reading or writing multiple entries with getMultiple() or setMultiple().
     *
     * But when using get() or set(), integer keys are clearly integers and are considered invalid.
     */
    protected function invalidKeyTypes(): array
    {
        return array_merge(
            $this->invalidArrayKeyTypes(),
            [
                ['key' => 0],
                ['key' => 2],
            ]);
    }

    protected function invalidArrayKeyTypes(): array
    {
        return [
            ['key' => new \stdClass()],
            ['key' => true],
            ['key' => false],
            ['key' => null],
            ['key' => 2.5],
            ['key' => ['array']],
        ];
    }

    protected function invalidKeys(): array
    {
        return [
            ['key' => ''],
            ['key' => '{str'],
            ['key' => 'rand{'],
            ['key' => 'rand{str'],
            ['key' => 'rand}str'],
            ['key' => 'rand(str'],
            ['key' => 'rand)str'],
            ['key' => 'rand/str'],
            ['key' => 'rand\\str'],
            ['key' => 'rand@str'],
            ['key' => 'rand:str'],
        ];
    }

    protected function invalidArrayKeys(): array
    {
        return array_merge(
            $this->invalidKeys(),
            $this->invalidArrayKeyTypes(),
        );
    }

    protected function invalidTtl(): array
    {
        return [
            ['ttl' => ''],
            ['ttl' => true],
            ['ttl' => false],
            ['ttl' => 'abc'],
            ['ttl' => 2.5],
            ['ttl' => ' 1'], // Could be cast to an int
            ['ttl' => '12foo'], // Could be cast to an int
            ['ttl' => '025'], // Could be interpreted as hex
            ['ttl' => new \stdClass()],
            ['ttl' => ['array']],
        ];
    }

    private function createGenerator(array $array): Generator
    {
        foreach ($array as $key => $value) {
            yield $key => $value;
        }
    }
}
