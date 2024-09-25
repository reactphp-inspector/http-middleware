<?php

declare(strict_types=1);

namespace ReactInspector\HttpMiddleware;

use WyriHaximus\Metrics\Factory;
use WyriHaximus\Metrics\Label;
use WyriHaximus\Metrics\Label\Name;
use WyriHaximus\Metrics\Registry;
use WyriHaximus\Metrics\Registry\Counters;
use WyriHaximus\Metrics\Registry\Gauges;
use WyriHaximus\Metrics\Registry\Summaries;

use function array_map;

final class Metrics
{
    /** @var array<Label> */
    private array $defaultLabels;

    public function __construct(private Gauges $inflight, private Counters $requests, private Summaries $responseTime, Label ...$defaultLabels)
    {
        $this->defaultLabels = $defaultLabels;
    }

    public static function create(Registry $registry, Label ...$defaultLabels): self
    {
        $defaultLabelNames = array_map(static fn (Label $label): Name => new Name($label->name()), $defaultLabels);

        return new self(
            $registry->gauge(
                'http_requests_inflight',
                'The number of HTTP requests that are currently inflight within the application',
                new Name('method'),
                ...$defaultLabelNames,
            ),
            $registry->counter(
                'http_requests',
                'The number of HTTP requests handled by HTTP request method and response status code',
                new Name('method'),
                new Name('code'),
                ...$defaultLabelNames,
            ),
            $registry->summary(
                'http_response_times',
                'The time it took to come to a response by HTTP request method and response status code',
                Factory::defaultQuantiles(),
                new Name('method'),
                new Name('code'),
                ...$defaultLabelNames,
            ),
            ...$defaultLabels,
        );
    }

    /** @return array<Label> */
    public function defaultLabels(): array
    {
        return $this->defaultLabels;
    }

    public function inflight(): Gauges
    {
        return $this->inflight;
    }

    public function requests(): Counters
    {
        return $this->requests;
    }

    public function responseTime(): Summaries
    {
        return $this->responseTime;
    }
}
