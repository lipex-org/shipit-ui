<?php
/**
 * @var $this \CodeIgniter\View\View
 */
?>

<!DOCTYPE html>
<html lang="en" class="bg-gray-950 text-gray-100">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $title ?? "Jengo Base App" ?></title>

    <script>
        window.siteUrl = '<?= rtrim(site_url(), '/') ?>';
        window.csrfHeader = '<?= csrf_header() ?>';
        window.csrfHash = '<?= csrf_hash() ?>';
    </script>

    <?= view('layouts/partials/header.layout.partial.php') ?>

    <?= $this->renderSection('header') ?>
</head>

<body class="min-h-screen bg-gray-950 selection:bg-blue-500/30">
    <?= $this->renderSection('content') ?>

    <?= view('layouts/partials/footer.layout.partial.php') ?>

    <?= $this->renderSection('footer') ?>
</body>
</html>
