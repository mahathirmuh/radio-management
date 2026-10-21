<?php

// The notifications that are written for a person now and then (App\Services\Notifications\NotificationSweep), in the
// language of whoever gets them.

return [
    'token_mine_title' => 'Your API token is about to expire',
    'token_mine_body' => 'Token ":name" expires :when. If it is still in use, create a new token under API Tokens.',
    'token_client_title' => 'An API client token is about to expire',
    'token_client_body' => 'Token ":name" of the API client :client expires :when. If the application still uses the API, create a new token for it under API Tokens.',
    'today' => 'today (:date)',
    'tomorrow' => 'tomorrow (:date)',
    'in_days' => '{1} in :days day (:date)|[2,*] in :days days (:date)',
    'weekly_title' => 'This week\'s radio register summary',
    'weekly_body' => 'Needs action: :issues.',
    'weekly_more' => '{1} and :count more check|[2,*] and :count more checks',
];
