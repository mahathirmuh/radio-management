<?php

// Laravel's messages of a password reset, in Indonesian (the English ones come with the framework). This application
// resets no password (Auth::routes without reset); they are here so that no message falls back to English.

return [
    'reset' => 'Kata sandi Anda sudah diganti.',
    'sent' => 'Tautan untuk mengganti kata sandi sudah dikirim ke email Anda.',
    'throttled' => 'Tunggu sebentar sebelum mencoba lagi.',
    'token' => 'Token penggantian kata sandi ini tidak valid.',
    'user' => 'Tidak ada pengguna dengan alamat email itu.',
];
