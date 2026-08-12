<?php

it('assigns a request id to every response', function () {
    $response = $this->get('/health');

    expect($response->headers->get('X-Request-Id'))
        ->not->toBeNull()
        ->toMatch('/^[A-Za-z0-9._\-]{8,64}$/');
});

it('propagates a well-formed client-supplied request id', function () {
    $response = $this->withHeaders(['X-Request-Id' => 'client-trace-0042'])
        ->get('/health');

    expect($response->headers->get('X-Request-Id'))->toBe('client-trace-0042');
});

it('replaces a malformed client-supplied request id', function () {
    $response = $this->withHeaders(['X-Request-Id' => 'bad id! with spaces'])
        ->get('/health');

    expect($response->headers->get('X-Request-Id'))
        ->not->toBe('bad id! with spaces')
        ->toMatch('/^[A-Za-z0-9._\-]{8,64}$/');
});

it('returns a request id even on error responses', function () {
    $response = $this->get('/definitely-missing-page');

    $response->assertNotFound();
    expect($response->headers->get('X-Request-Id'))->not->toBeNull();
});
