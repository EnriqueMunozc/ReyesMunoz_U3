<?php

return [
    // API solo Bearer. La web mantiene sesiones y CSRF independientes.
    'stateful' => [], 'guard' => [], 'expiration' => 480, 'token_prefix' => '',
    'middleware' => [],
];
