{{-- Shown inside the file viewer's <iframe> when a file cannot be shown there: a page of its own, without the layout. --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ __('SharePointDocs::common.file_unavailable') }}</title>
    <style>
        body {
            margin: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: system-ui, -apple-system, "Segoe UI", sans-serif;
            color: #49566c;
            background: #f6f8fb;
        }

        p {
            max-width: 32rem;
            margin: 1.5rem;
            text-align: center;
            line-height: 1.5;
        }
    </style>
</head>

<body>
    <p id="fileMessage">{{ $message }}</p>
</body>

</html>
