<?php

// The notifications that are written for a person now and then (App\Services\Notifications\NotificationSweep), in the
// language of whoever gets them.

return [
    'token_mine_title' => 'Token API Anda akan berakhir',
    'token_mine_body' => 'Token ":name" berakhir :when. Bila masih dipakai, buat token baru di API Tokens.',
    'token_client_title' => 'Token API client akan berakhir',
    'token_client_body' => 'Token ":name" milik API client :client berakhir :when. Bila aplikasinya masih memakai API, buatkan token baru di API Tokens.',
    'today' => 'hari ini (:date)',
    'tomorrow' => 'besok (:date)',
    'in_days' => 'dalam :days hari (:date)',
    'weekly_title' => 'Ringkasan register radio minggu ini',
    'weekly_body' => 'Perlu tindakan: :issues.',
    'weekly_more' => 'dan :count cek lain',
];
