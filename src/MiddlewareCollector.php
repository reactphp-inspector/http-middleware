<?php declare(strict_types=1);

namespace ReactInspector\HttpMiddleware;

use function ApiClients\Tools\Rx\observableFromArray;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use React\Promise\PromiseInterface;
use function React\Promise\resolve;
use ReactInspector\CollectorInterface;
use ReactInspector\Config;
use ReactInspector\Measurement;
use ReactInspector\Metric;
use ReactInspector\Tag;
use Rx\Observable;

final class MiddlewareCollector implements CollectorInterface
{
    /** @var string */
    private $server;
    private $inflight = [];
    private $requests = [];

    public function __construct(string $server)
    {
        $this->server = $server;
    }

    public function __invoke(ServerRequestInterface $request, callable $next): PromiseInterface
    {
        $method = \strtoupper($request->getMethod());
        if (!\array_key_exists($method, $this->inflight)) {
            $this->inflight[$method] = 0;
            $this->requests[$method] = [];
        }
        $this->inflight[$method]++;

        return resolve($next($request))->then(function (ResponseInterface $response) use ($method): ResponseInterface {
            $this->inflight[$method]--;

            $code = $response->getStatusCode();
            if (!\array_key_exists($code, $this->requests[$method])) {
                $this->requests[$method][$code] = 0;
            }
            $this->requests[$method][$code]++;

            return $response;
        });
    }

    public function collect(): Observable
    {
        return observableFromArray([
            new Metric(
                new Config(
                    'http_requests_inflight',
                    'gauge',
                    'The number of HTTP requests that are currently inflight within the application'
                ),
                [
                    new Tag('server', $this->server),
                ],
                (static function (array $inflight) {
                    $methods = [];

                    foreach ($inflight as $method => $count) {
                        $methods[] = new Measurement(
                            $count,
                            new Tag('method', $method)
                        );
                    }

                    return $methods;
                })($this->inflight),
            ),
            new Metric(
                new Config(
                    'http_requests_total',
                    'counter',
                    'The number of HTTP requests handled by HTTP request method and response status code'
                ),
                [
                    new Tag('server', $this->server),
                ],
                (static function (array $requests) {
                    $methods = [];

                    foreach ($requests as $method => $codes) {
                        foreach ($codes as $code => $count) {
                            $methods[] = new Measurement(
                                $count,
                                new Tag('method', $method),
                                new Tag('code', (string)$code),
                            );
                        }
                    }

                    return $methods;
                })($this->requests),
            ),
        ]);
    }

    public function cancel(): void
    {
        // void
    }
}
