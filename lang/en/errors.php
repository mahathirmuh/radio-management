<?php

// The pages of an error (resources/views/errors) and the page shown offline.

return [
    'forbidden' => [
        'title' => 'Access denied',
        'heading' => 'You do not have access to this page',
        'reason' => 'Your role has not been given access to this menu.',
        'ask' => 'Contact the application admin if you need this access.',
        'home' => 'To the home page',
        'sign_out' => 'Sign out',
    ],
    'not_found' => [
        'title' => 'Page not found',
        'heading' => 'Page not found',
        'redirect' => 'redirecting to an active page...',
    ],
    'maintenance' => [
        'title' => 'Under maintenance',
        'heading' => 'We are currently under maintenance',
    ],
    'offline' => [
        'title' => 'Offline',
        'heading' => 'You are offline!',
        'text' => 'Please check your internet connection.',
    ],
];
