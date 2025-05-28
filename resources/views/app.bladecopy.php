
    <!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Aqua Taxi</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    @php
        $manifest = json_decode(file_get_contents(public_path('build/manifest.json')), true);
        $entry = $manifest['resources/js/app.js'] ?? null;
    @endphp

    @if ($entry && isset($entry['css']))
        @foreach ($entry['css'] as $css)
            <link rel="stylesheet" href="{{ asset('build/' . $css) }}">
        @endforeach
    @endif
</head>
<body class="antialiased">
<div id="app"></div>

@if ($entry)
    <script type="module" src="{{ asset('build/' . $entry['file']) }}"></script>
@endif
</body>
</html>
