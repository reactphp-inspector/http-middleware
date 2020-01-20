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
use ReactInspector\Measurements;
use ReactInspector\Metric;
use ReactInspector\Tag;
use ReactInspector\Tags;
use Rx\Observable;

final class MiddlewareCollector implements CollectorInterface
{
    public const TAGS_ATTRIBUTE = 'mnbhasdhkndsajkhdsahjksadjkhdsajkhdsa';

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
        $tags = new Tags(new Tag('method', $method));
        $request = $request->withAttribute(self::TAGS_ATTRIBUTE, $tags);
        if (!\array_key_exists($method, $this->inflight)) {
            $this->inflight[$method] = 0;
        }
        $this->inflight[$method]++;

        return resolve($next($request))->then(function (ResponseInterface $response) use ($method, $tags): ResponseInterface {
            $this->inflight[$method]--;

            $code = $response->getStatusCode();
            $tags->add(new Tag('code', (string)$code));
            $tags = (string)$tags;
            if (!\array_key_exists($tags, $this->requests)) {
                $this->requests[$tags] = 0;
            }
            $this->requests[$tags]++;

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
                new Tags(
                    new Tag('server', $this->server),
                ),
                (static function (array $inflight): Measurements {
                    $methods = [];

                    foreach ($inflight as $method => $count) {
                        $methods[] = new Measurement(
                            $count,
                            new Tags(new Tag('method', $method))
                        );
                    }

                    return new Measurements(...$methods);
                })($this->inflight),
            ),
            new Metric(
                new Config(
                    'http_requests_total',
                    'counter',
                    'The number of HTTP requests handled by HTTP request method and response status code'
                ),
                new Tags(
                    new Tag('server', $this->server),
                ),
                (static function (array $requests): Measurements {
                    $measurements = [];

                    foreach ($requests as $tag => $count) {
                        $measurements[] = new Measurement(
                            $count,
                            new Tags(...\array_values(Tags::fromString($tag)->get()))
                        );
                    }

                    return new Measurements(...$measurements);
                })($this->requests),
            ),
        ]);
    }

    public function cancel(): void
    {
        // void
    }
}
