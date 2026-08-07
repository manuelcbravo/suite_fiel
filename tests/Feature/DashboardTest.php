<?php

use App\Models\User;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

// No hay un dashboard propio: la ruta manda al primer tablero disponible.
test('authenticated users land on the first tablero', function () {
    $this->actingAs(User::factory()->create());

    $this->get(route('dashboard'))->assertRedirect(route('tableros.ejecutivo'));
});
