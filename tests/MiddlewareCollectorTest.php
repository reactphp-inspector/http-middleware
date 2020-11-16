<?php

declare(strict_types=1);

namespace ReactInspector\Tests\HttpMiddleware;

use Psr\Http\Message\ResponseInterface;
use React\Promise\Deferred;
use React\Promise\PromiseInterface;
use ReactInspector\HttpMiddleware\MiddlewareCollector;
use ReactInspector\Metric;
use RingCentral\Psr7\Response;
use RingCentral\Psr7\ServerRequest;
use Rx\React\Promise;
use WyriHaximus\AsyncTestUtilities\AsyncTestCase;

use function array_key_exists;

/**
 * @internal
 */
final class MiddlewareCollectorTest extends AsyncTestCase
{
    public function testCollectMetrics(): void
    {
        $collector = new MiddlewareCollector('test');

        /** @var Metric[] $metrics */
        $metrics = $this->await(Promise::fromObservable($collector->collect()->toArray()));
        self::assertCount(2, $metrics);
        foreach ($metrics as $metric) {
            self::assertCount(1, $metric->tags()->get());
            self::assertCount(0, $metric->measurements()->get());
        }

        $this->await($collector(new ServerRequest('get', 'https://example.com/'), static function (): ResponseInterface {
            return new Response();
        }));
        $collector(new ServerRequest('GET', 'https://example.com/'), static function (): PromiseInterface {
            return (new Deferred())->promise();
        });

        /** @var Metric[] $metrics */
        $metrics = $this->await(Promise::fromObservable($collector->collect()->toArray()));
        self::assertCount(2, $metrics);
        foreach ($metrics as $metric) {
            self::assertCount(1, $metric->tags()->get());
            self::assertCount(1, $metric->measurements()->get());
            self::assertSame(1.0, $metric->measurements()->get()[0]->value());
            if (! array_key_exists('code', $metric->tags()->get())) {
                continue;
            }

            self::assertSame('200', $metric->tags()->get()['code']->value());
        }
    }
}
