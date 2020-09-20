<?php

declare(strict_types=1);

namespace ReactInspector\HttpMiddleware;

use WyriHaximus\Metrics\Factory;
use WyriHaximus\Metrics\Label;
use WyriHaximus\Metrics\Registry;

use function array_map;

final class Metrics
{
    /** @var array<Label> */
    private array $defaultLabels;

    private Registry\Gauges $inflight;
    private Registry\Counters $requests;
    private Registry\Summaries $responseTime;

    public function __construct(Registry\Gauges $inflight, Registry\Counters $requests, Registry\Summaries $responseTime, Label ...$defaultLabels)
    {
        $this->inflight      = $inflight;
        $this->requests      = $requests;
        $this->responseTime  = $responseTime;
        $this->defaultLabels = $defaultLabels;
    }

    public static function create(Registry $registry, Label ...$defaultLabels): self
    {
        $defaultLabelNames = array_map(static fn (Label $label): Label\Name => new Label\Name($label->name()), $defaultLabels);

        return new self(
            $registry->gauge(
                'http_requests_inflight',
                'The number of HTTP requests that are currently inflight within the application',
                new Label\Name('method'),
                ...$defaultLabelNames
            ),
            $registry->counter(
                'http_requests',
                'The number of HTTP requests handled by HTTP request method and response status code',
                new Label\Name('method'),
                new Label\Name('code'),
                ...$defaultLabelNames
            ),
            $registry->summary(
                'http_response_times',
                'The time it took to come to a response by HTTP request method and response status code',
                Factory::defaultQuantiles(),
                new Label\Name('method'),
                new Label\Name('code'),
                ...$defaultLabelNames
            ),
            ...$defaultLabels
        );
    }

    /**
     * @return array<Label>
     */
    public function defaultLabels(): array
    {
        return $this->defaultLabels;
    }

    public function inflight(): Registry\Gauges
    {
        return $this->inflight;
    }

    public function requests(): Registry\Counters
    {
        return $this->requests;
    }

    public function responseTime(): Registry\Summaries
    {
        return $this->responseTime;
    }
}
