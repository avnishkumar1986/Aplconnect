<?php

use App\Jobs\ImportMaterialsFromApi;
use GuzzleHttp\Psr7\Response as PsrResponse;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Tests\TestCase;

uses(TestCase::class);

test('API retry backoff is bounded', function () {
    $job = new ImportMaterialsFromApi(apiId: 1);
    $method = new ReflectionMethod($job, 'retryDelayMilliseconds');

    expect($method->invoke($job, 1, 2))->toBe(2_000)
        ->and($method->invoke($job, 2, 2))->toBe(4_000)
        ->and($method->invoke($job, 10, 300))->toBe(30_000);
});

test('only transient HTTP responses are retryable', function () {
    $job = new ImportMaterialsFromApi(apiId: 1);
    $method = new ReflectionMethod($job, 'shouldRetryApiRequest');

    $serviceUnavailable = new RequestException(new Response(new PsrResponse(503)));
    $unauthorized = new RequestException(new Response(new PsrResponse(401)));

    expect($method->invoke($job, $serviceUnavailable))->toBeTrue()
        ->and($method->invoke($job, $unauthorized))->toBeFalse();
});
