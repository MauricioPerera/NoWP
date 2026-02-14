<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title ?? 'Default Theme') ?></title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            line-height: 1.6;
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
            color: #333;
        }
        h1 { color: #2c3e50; }
        .content { margin-top: 20px; }
    </style>
</head>
<body>
    <header>
        <h1><?= htmlspecialchars($title ?? 'Welcome') ?></h1>
    </header>
    
    <main class="content">
        <?= $content ?? '<p>No content available.</p>' ?>
    </main>
    
    <footer>
        <p>&copy; <?= date('Y') ?> - Powered by Framework</p>
    </footer>
</body>
</html>
