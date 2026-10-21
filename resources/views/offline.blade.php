<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <title>{{ __('errors.offline.title') }}</title>
</head>
<body>
    <h1>{{ __('errors.offline.heading') }}</h1>
    <p>{{ __('errors.offline.text') }}</p>
</body>
</html>
