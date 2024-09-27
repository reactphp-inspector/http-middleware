<?php

declare(strict_types=1);

namespace ReactInspector\HttpMiddleware;

use WyriHaximus\Metrics\Label;

final class Labels
{
    /** @var array<Label> */
    private array $labels = [];

    public function add(Label $label): void
    {
        $this->labels[] = $label;
    }

    // FFS PHP CS This is not a constructor
    // phpcs:disable
    /**
     * @return iterable<Label>
     */
    public function labels(): iterable
    {
        yield from $this->labels;
    }
}
