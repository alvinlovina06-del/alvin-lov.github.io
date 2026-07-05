<?php
// Variables expected: $pageTitle, $pageClass, $extraCss, $extraJs
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="UASKTE App - Secure User Management System">
    <title><?= htmlspecialchars($pageTitle ?? 'UASKTE App') ?></title>
    <link rel="manifest" href="<?= $basePath ?? './' ?>manifest.json">
    <meta name="theme-color" content="#002398">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=JetBrains+Mono&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= $basePath ?? './' ?>assets/css/style.css">
    <link rel="apple-touch-icon" href="<?= $basePath ?? './' ?>assets/img/icon-192.png">
    <?php if (!empty($extraCss)): ?>
    <style><?= $extraCss ?></style>
    <?php endif; ?>
    <script>window.API_BASE = '<?= $basePath ?? "./" ?>api/';</script>
</head>
<body class="<?= htmlspecialchars($pageClass ?? '') ?>">
    
