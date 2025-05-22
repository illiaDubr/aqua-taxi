@php
    $manifest = json_decode(file_get_contents(public_path('build/manifest.json')), true);
@endphp

    <!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Aqua Taxi</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <link rel="stylesheet" href="{{ asset('build/' . $manifest['resources/js/app.js']['css'][0]) }}">
</head>
<body class="antialiased">
<div id="app"></div>

<script type="module" src="{{ asset('build/' . $manifest['resources/js/app.js']['file']) }}"></script>
</body>
</html>
