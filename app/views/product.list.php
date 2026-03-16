<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Products</title>
    <style>
        body { font-family: sans-serif; max-width: 800px; margin: 40px auto; padding: 0 20px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { text-align: left; padding: 10px 12px; border-bottom: 1px solid #ddd; }
        th { background: #f5f5f5; }
        .badge { padding: 2px 8px; border-radius: 4px; font-size: 0.8em; }
        .in-stock { background: #d4edda; color: #155724; }
        .low-stock { background: #fff3cd; color: #856404; }
        .out-of-stock { background: #f8d7da; color: #721c24; }
        a { color: #0066cc; text-decoration: none; }
    </style>
</head>
<body>
    <h1>Products</h1>

    <?php if (empty($products)): ?>
        <p>No products found.</p>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Name</th>
                    <th>Price</th>
                    <th>Stock</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($products as $product): ?>
                    <?php
                        if ($product->stock === 0) {
                            $badge = 'out-of-stock'; $label = 'Out of stock';
                        } elseif ($product->stock < 5) {
                            $badge = 'low-stock'; $label = 'Low stock';
                        } else {
                            $badge = 'in-stock'; $label = $product->stock . ' in stock';
                        }
                    ?>
                    <tr>
                        <td><?= $product->id ?></td>
                        <td><a href="/product/show/<?= $product->id ?>"><?= htmlspecialchars($product->name) ?></a></td>
                        <td>$<?= number_format($product->price, 2) ?></td>
                        <td><span class="badge <?= $badge ?>"><?= $label ?></span></td>
                        <td>
                            <a href="/product/show/<?= $product->id ?>">View</a>
                            <form method="POST" action="/product/delete/<?= $product->id ?>" style="display:inline">
                                <button type="submit" onclick="return confirm('Delete <?= htmlspecialchars($product->name) ?>?')">Delete</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <hr>
    <h2>Add Product</h2>
    <form method="POST" action="/product/create">
        <label>Name: <input type="text" name="name" required></label><br><br>
        <label>Price: <input type="number" name="price" step="0.01" min="0" required></label><br><br>
        <label>Stock: <input type="number" name="stock" min="0" required></label><br><br>
        <button type="submit">Create</button>
    </form>
</body>
</html>
