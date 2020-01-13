<?php declare(strict_types=1);

namespace ReactInspector\Tests\HttpMiddleware;

use ReactInspector\HttpMiddleware\MiddlewareCollector;
use ReactInspector\Metric;
use RingCentral\Psr7\Response;
use RingCentral\Psr7\ServerRequest;
use Rx\React\Promise;
use WyriHaximus\AsyncTestUtilities\AsyncTestCase;

/**
 * @internal
 */
final class MemoryUsageCollectorTest extends AsyncTestCase
{
    public function testCollectMetrics(): void
    {
        $collector = new MiddlewareCollector('test');

        /** @var Metric[] $metric */
        $metrics = $this->await(Promise::fromObservable($collector->collect()->toArray()));
        self::assertCount(2, $metrics);
        /** @var Metric $metric */
        foreach ($metrics as $metric) {
            self::assertCount(1, $metric->tags());
            self::assertCount(0, $metric->measurements());
        }

        $this->await($collector(new ServerRequest('GET', 'https://example.com/'), function () {
            return new Response();
        }));

        /** @var Metric[] $metric */
        $metrics = $this->await(Promise::fromObservable($collector->collect()->toArray()));
        self::assertCount(2, $metrics);
        /** @var Metric $metric */
        foreach ($metrics as $metric) {
            self::assertCount(1, $metric->tags());
            self::assertCount(1, $metric->measurements());
        }
    }
}
