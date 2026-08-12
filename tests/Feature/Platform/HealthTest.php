<?php

it('reports application health with component checks', function () {
    $response = $this->get('/health');

    $response->assertOk()
        ->assertJsonPath('status', 'ok')
        ->assertJsonPath('checks.database', true)
        ->assertJsonPath('checks.cache', true)
        ->assertJsonStructure(['status', 'checks', 'version']);
});

it('serves the framework liveness endpoint', function () {
    $this->get('/up')->assertOk();
});
