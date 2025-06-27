<?php

declare(strict_types=1);

namespace GabeSullice\Snakepit;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Pool;
use GuzzleHttp\Promise\PromiseInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Pit wraps a Guzzle Pool to process requests asynchronously using PHP Fibers.
 *
 * Fiber Pool => 🐍 pit.
 */
final readonly class Pit {

  /**
   * Raised when the given $requests iterator violates Pool's requirements.
   *
   * @see \GuzzleHttp\Pool::__construct()
   */
  private const string GUZZLE_EXCEPTION_MSG = 'Each value yielded by the iterator must be a callable that returns a promise that fulfills with a Psr7\Message\Http\ResponseInterface object.';

  /**
   * Constructs a new Pit object.
   *
   * @see \GuzzleHttp\Pool::__construct()
   */
  public function __construct(
    private ClientInterface $client,
    private array|\Iterator $requests,
    private array $config = [],
  ) {}

  /**
   * Returns a generator that yields asynchronously fetched responses.
   */
  public function process(): \Generator {
    $wrappedRequests = function (): \Generator {
      foreach ($this->requests as $req) {
        yield function () use ($req) {
          return $this->getPromise($req)->then(function ($response) {
            if (!$response instanceof ResponseInterface) {
              throw new \InvalidArgumentException(self::GUZZLE_EXCEPTION_MSG);
            }
            \Fiber::suspend($response);
            // There shouldn't be any use for this return value, but it is
            // returned anyways in order to satisfy's Pool's interface.
            return $response;
          });
        };
      }
    };
    $pool = new Pool($this->client, $wrappedRequests(), $this->config);
    $fiber = new \Fiber(fn() => $pool->promise()->wait());
    $response = $fiber->start();
    while ($response) {
      yield $response;
      $response = $fiber->resume();
    }
  }

  /**
   * Transforms a request into a promise, if needed.
   *
   * @param mixed $req
   *   A request object or a promise that resolves a response.
   *
   * @throws \InvalidArgumentException
   *   Throws the same exception as thrown by {@link Pool::__construct}.
   */
  private function getPromise(mixed $req): PromiseInterface {
    // This type check is adapted from {@link \GuzzleHttp\Pool::__construct()}
    if ($req instanceof RequestInterface) {
      return $this->client->sendAsync($req, $this->config);
    }
    elseif (\is_callable($req)) {
      $promise = $req();
      if ($promise instanceof PromiseInterface) {
        return $promise;
      }
    }
    throw new \InvalidArgumentException(self::GUZZLE_EXCEPTION_MSG);
  }

}
