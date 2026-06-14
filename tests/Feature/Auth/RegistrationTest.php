<?php

test('registration screen is disabled and returns 404', function () {
    $response = $this->get('/register');

    $response->assertStatus(404);
});
