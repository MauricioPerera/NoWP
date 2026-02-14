<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($content->title ?? 'Post') ?></title>
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
        .meta { color: #7f8c8d; font-size: 0.9em; margin-bottom: 20px; }
        .content { margin-top: 20px; }
    </style>
</head>
<body>
    <article>
        <header>
            <h1><?= htmlspecialchars($content->title ?? 'Untitled') ?></h1>
            <div class="meta">
                <?php if (isset($content->publishedAt)): ?>
                    Published: <?= $content->publishedAt->format('F j, Y') ?>
                <?php endif; ?>
            </div>
        </header>
        
        <div class="content">
            <?= $content->content ?? '<p>No content available.</p>' ?>
        </div>
    </article>
    
    <footer>
        <p><a href="/">&larr; Back to home</a></p>
    </footer>
</body>
</html>
