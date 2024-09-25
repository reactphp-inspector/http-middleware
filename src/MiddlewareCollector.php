<?php

declare(strict_types=1);

namespace ReactInspector\HttpMiddleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use React\Promise\PromiseInterface;
use WyriHaximus\Metrics\Label;
use WyriHaximus\Metrics\Registry\Counters;
use WyriHaximus\Metrics\Registry\Gauges;
use WyriHaximus\Metrics\Registry\Summaries;

use function React\Promise\resolve;
use function Safe\hrtime;
use function strtoupper;

final class MiddlewareCollector
{
    public const TAGS_ATTRIBUTE = '25376cd37c51a221b5b0a82dd0b2f4f6';

    /** @var array<Label> */
    private array $defaultLabels;

    private Gauges $inflight;

    private Counters $requests;

    private Summaries $responseTime;

    public function __construct(Metrics $metrics)
    {
        $this->defaultLabels = $metrics->defaultLabels();
        $this->inflight      = $metrics->inflight();
        $this->requests      = $metrics->requests();
        $this->responseTime  = $metrics->responseTime();
    }

    /** @return PromiseInterface<ResponseInterface> */
    public function __invoke(ServerRequestInterface $request, callable $next): PromiseInterface
    {
        $method = strtoupper($request->getMethod());
        $gauge  = $this->inflight->gauge(new Label('method', $method), ...$this->defaultLabels);
        $gauge->incr();
        $time = hrtime(true);

        return resolve($next($request))->then(function (ResponseInterface $response) use ($method, $gauge, $time): ResponseInterface {
            /** @psalm-suppress PossiblyInvalidOperand */
            $this->responseTime->summary(
                new Label('method', $method),
                new Label('code', (string) $response->getStatusCode()),
                ...$this->defaultLabels,
            )->observe(
                (hrtime(true) - $time) / 1e+9, /** @phpstan-ignore-line */
            );
            $this->requests->counter(
                new Label('method', $method),
                new Label('code', (string) $response->getStatusCode()),
                ...$this->defaultLabels,
            )->incr();
            $gauge->dcr();

            return $response;
        });
    }
}
