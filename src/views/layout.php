<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Camagru</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-slate-50 text-slate-800">
    <div class="border-b border-slate-200 bg-white shadow-sm">
        <?php require __DIR__ . '/partials/header.php'; ?>
    </div>
    <main class="max-w-2xl mx-auto px-4 py-8">
        <?php require __DIR__ . '/' . $view; ?>
    </main>
    <div class="border-t border-slate-200 bg-white mt-auto">
        <?php require __DIR__ . '/partials/footer.php'; ?>
    </div>
</body>
</html>
