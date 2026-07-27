<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Operix</title>
    <!-- Prevent dark mode flash -->
    <script>
        if (localStorage.getItem('operix_dark') === 'true') {
            document.documentElement.classList.add('dark')
        }
    </script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="antialiased">
    <div id="app"></div>
</body>
</html>
