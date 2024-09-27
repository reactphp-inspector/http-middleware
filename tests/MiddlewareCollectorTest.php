<?php

declare(strict_types=1);

namespace ReactInspector\Tests\HttpMiddleware;

use Nyholm\Psr7\Response;
use Nyholm\Psr7\ServerRequest;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use React\Promise\Deferred;
use React\Promise\PromiseInterface;
use ReactInspector\HttpMiddleware\Metrics;
use ReactInspector\HttpMiddleware\MiddlewareCollector;
use WyriHaximus\AsyncTestUtilities\AsyncTestCase;
use WyriHaximus\Metrics\Factory;
use WyriHaximus\Metrics\Label;
use WyriHaximus\Metrics\Printer\Prometheus;

final class MiddlewareCollectorTest extends AsyncTestCase
{
    /** @test */
    public function collectMetrics(): void
    {
        $registry  = Factory::create();
        $collector = new MiddlewareCollector(Metrics::create($registry, new Label('server', 'test')));

        $metrics = $registry->print(new Prometheus());
        self::assertSame("\n\n\n# EOF\n", $metrics);

        $collector(new ServerRequest('get', 'https://example.com/'), static function (ServerRequestInterface $request): ResponseInterface {
            /** @phpstan-ignore-next-line This can blow up, which is to be expected as this is a feature that should work. */
            $request->getAttribute(MiddlewareCollector::LABELS_ATTRIBUTE)->add(new Label('foo', 'bar'));

            return new Response();
        });
        $collector(new ServerRequest('GET', 'https://example.com/'), static function (ServerRequestInterface $request): PromiseInterface {
            /** @phpstan-ignore-next-line This can blow up, which is to be expected as this is a feature that should work. */
            $request->getAttribute(MiddlewareCollector::LABELS_ATTRIBUTE)->add(new Label('foo', 'bar'));

            return (new Deferred())->promise();
        });

        $metrics = $registry->print(new Prometheus());
        self::assertStringContainsString('http_requests_total{code="200",foo="bar",method="GET",server="test"} 1', $metrics);
        self::assertStringContainsString('http_requests_inflight{method="GET",server="test"} 1', $metrics);
        self::assertStringContainsString('http_response_times{quantile="0.1",code="200",foo="bar",method="GET",server="test"}', $metrics);
        self::assertStringContainsString('http_response_times{quantile="0.5",code="200",foo="bar",method="GET",server="test"}', $metrics);
        self::assertStringContainsString('http_response_times{quantile="0.9",code="200",foo="bar",method="GET",server="test"}', $metrics);
        self::assertStringContainsString('http_response_times{quantile="0.99",code="200",foo="bar",method="GET",server="test"}', $metrics);
    }
}
