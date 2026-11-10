<?php

test('the application boots and reports healthy', function () {
    $this->get('/up')->assertStatus(200);
});

test('admin endpoints reject requests without the admin key', function () {
    $this->getJson('/api/v1/healthtalk/content/all')->assertStatus(401);
});

test('user endpoints reject requests without a token', function () {
    $this->getJson('/api/v1/healthtalk/sessions')->assertStatus(401);
});
