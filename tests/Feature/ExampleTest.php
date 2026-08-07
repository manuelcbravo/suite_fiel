<?php

// La raíz no tiene pantalla propia: la suite entra por el login.
test('the root sends visitors to the login screen', function () {
    $this->get(route('home'))->assertRedirect(route('login'));
});
