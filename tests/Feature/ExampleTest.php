<?php

use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config([
        'services.aira_api.url' => 'http://aira-api.test',
        'services.aira_api.key' => 'test-admin-key',
    ]);
    $this->withoutVite();
});

test('the home page redirects to the content library', function () {
    $this->get('/')->assertRedirect('/healthtalk/content');
});

test('every admin page opens without a login', function (string $url) {
    Http::fake(['*' => Http::response(['success' => true, 'data' => []], 200)]);

    $this->get($url)->assertOk();
})->with([
    '/healthtalk/content',
    '/healthtalk/guardrails',
    '/healthtalk/start-pitch',
    '/healthtalk/reports',
    '/sh-users',
]);

test('API calls carry the admin key and no bearer token', function () {
    Http::fake(['*' => Http::response(['data' => []], 200)]);

    $this->get('/healthtalk/content')->assertOk();

    Http::assertSent(fn ($request) => $request->url() === 'http://aira-api.test/api/v1/healthtalk/content/all'
        && $request->header('X-Admin-Key') === ['test-admin-key']
        && ! $request->hasHeader('Authorization'));
});

test('unknown pages return 404', function () {
    $this->get('/dashboard')->assertNotFound();
});
