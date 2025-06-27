Snakepit 🐍
---

Snakepit enables you to concurrently process Guzzle HTTP responses in an
easy-to-read, imperative loop.

## Why?

Snakepit allows your processing code to integrated into the rest
of your codebase without [coloring][function-color] the rest of your project.

By contrast, when you use Guzzle on its own, your processing must be either:

- Evaluated once _all_ responses have been resolved, or
- Evaluated in a `Promise::then()` callable, where it's disconnected from the
  natural flow of your calling code

## Usage

```php
use GabeSullice\Snakepit\Pit;
use GuzzleHttp\Client;

// Instantiate a Guzzle client.
$client = new Client();

// Instantiate a pit in same way you would instantiate a \GuzzleHttp\Pool.
$pit = new Pit($client, function () {
  for ($i = 0; $i < 10; $i++) {
    yield new Request('GET', 'https://example.com');
  }
}, ['concurrency' => 10]);

foreach ($pit->process() as $response) {
  // Process each response as soon as it becomes available.
}
```

## How it works

Snakepit uses Guzzle's [Pool class][guzzle-pool] to send multiple concurrent
requests and a PHP [Fiber][fibers] to interrupt its asynchronous Promise
evaluation. Then, each fulfilled response is passed back to your code for
processing via a PHP [Generator][generators].

## Error handling

The simplest way to handle errors is to fall back to the promise pattern.

```php
// Instead of yielding a request, yield a callable returning a caught promise.
$pit = new Pit($client, function () {
  for ($i = 0; $i < 10; $i++) {
    yield fn () => $client
      ->requestAsync('GET', 'https://example.com')
      ->otherwise(fn ($reason) => /* handle error */);
  }
}, ['concurrency' => 10]);
```

## _Snakepit_?

Fiber + Pool = 🐍 Snake + pit

[fibers]: https://www.php.net/manual/en/language.fibers.php
[function-color]: https://journal.stuffwithstuff.com/2015/02/01/what-color-is-your-function/
[generators]: https://www.php.net/manual/en/language.generators.overview.php
[guzzle-pool]: https://docs.guzzlephp.org/en/stable/quickstart.html#concurrent-requests
