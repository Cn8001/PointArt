<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($product) ? htmlspecialchars($product->name) : 'Product' ?></title>
    <style>
        body { font-family: sans-serif; max-width: 600px; margin: 40px auto; padding: 0 20px; }
        .field { margin-bottom: 16px; }
        label { display: block; font-weight: bold; margin-bottom: 4px; color: #555; }
        input { width: 100%; padding: 8px; box-sizing: border-box; border: 1px solid #ccc; border-radius: 4px; }
        button { padding: 8px 16px; cursor: pointer; }
        .btn-save { background: #0066cc; color: white; border: none; border-radius: 4px; }
        .btn-delete { background: #dc3545; color: white; border: none; border-radius: 4px; }
        a { color: #0066cc; }
    </style>
</head>
<body>
    <p><a href="/product/list">← Back to list</a></p>

    <?php if (isset($product)): ?>
        <h1><?= htmlspecialchars($product->name) ?></h1>

        <form method="POST" action="/product/update/<?= $product->id ?>">
            <div class="field">
                <label>Name</label>
                <input type="text" value="<?= htmlspecialchars($product->name) ?>" disabled>
            </div>
            <div class="field">
                <label>Price ($)</label>
                <input type="number" name="price" step="0.01" min="0"
                       value="<?= $product->price ?>" required>
            </div>
            <div class="field">
                <label>Stock</label>
                <input type="number" name="stock" min="0"
                       value="<?= $product->stock ?>" required>
            </div>
            <button type="submit" class="btn-save">Save changes</button>
        </form>

        <hr>
        <form method="POST" action="/product/delete/<?= $product->id ?>">
            <button type="submit" class="btn-delete"
                    onclick="return confirm('Delete <?= htmlspecialchars($product->name) ?>?')">
                Delete product
            </button>
        </form>
    <?php endif; ?>
</body>
</html>
