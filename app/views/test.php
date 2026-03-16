<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test View</title>
</head>
<body>
    <h1> H1 </h1>
    <p>This is a test</p>
    <?php if (isset($id)): ?><p>Id: <?= htmlspecialchars($id) ?></p><?php endif; ?>
</body>
</html>
