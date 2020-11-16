<?php

declare(strict_types=1);

namespace ReactInspector\Tests\HttpMiddleware;

use Psr\Http\Message\ResponseInterface;
use React\Promise\Deferred;
use React\Promise\PromiseInterface;
use ReactInspector\HttpMiddleware\Metrics;
use ReactInspector\HttpMiddleware\MiddlewareCollector;
use RingCentral\Psr7\Response;
use RingCentral\Psr7\ServerRequest;
use WyriHaximus\AsyncTestUtilities\AsyncTestCase;
use WyriHaximus\Metrics\Factory;
use WyriHaximus\Metrics\Label;
use WyriHaximus\Metrics\Printer\Prometheus;

use function Safe\sleep;

final class MiddlewareCollectorTest extends AsyncTestCase
{
    /**
     * @test
     */
    public function collectMetrics(): void
    {
        $registry  = Factory::create();
        $collector = new MiddlewareCollector(Metrics::create($registry, new Label('server', 'test')));

        $metrics = $registry->print(new Prometheus());
        self::assertSame("\n\n\n", $metrics);

        $collector(new ServerRequest('get', 'https://example.com/'), static function (): ResponseInterface {
            sleep(1);

            return new Response();
        });
        $collector(new ServerRequest('GET', 'https://example.com/'), static function (): PromiseInterface {
            return (new Deferred())->promise();
        });

        $metrics = $registry->print(new Prometheus());
        self::assertStringContainsString('http_requests_total{code="200",method="GET",server="test"} 1', $metrics);
        self::assertStringContainsString('http_requests_inflight{method="GET",server="test"} 1', $metrics);
        self::assertStringContainsString('http_response_times{quantile="0.1",code="200",method="GET",server="test"} 1.00', $metrics);
        self::assertStringContainsString('http_response_times{quantile="0.99",code="200",method="GET",server="test"} 1.00', $metrics);
    }
}
