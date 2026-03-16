<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Users</title>
    <style>
        body { font-family: sans-serif; max-width: 800px; margin: 40px auto; padding: 0 20px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { text-align: left; padding: 10px 12px; border-bottom: 1px solid #ddd; }
        th { background: #f5f5f5; }
        a { color: #0066cc; text-decoration: none; }
        button { cursor: pointer; }
    </style>
</head>
<body>
    <h1>Users</h1>

    <?php if (empty($users)): ?>
        <p>No users found.</p>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $user): ?>
                    <tr>
                        <td><?= $user->id ?></td>
                        <td><a href="/user/show/<?= $user->id ?>"><?= htmlspecialchars($user->name) ?></a></td>
                        <td><?= htmlspecialchars($user->email) ?></td>
                        <td>
                            <a href="/user/show/<?= $user->id ?>">View</a>
                            <form method="POST" action="/user/delete/<?= $user->id ?>" style="display:inline">
                                <button type="submit" onclick="return confirm('Delete <?= htmlspecialchars($user->name) ?>?')">Delete</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <hr>
    <h2>Add User</h2>
    <form method="POST" action="/user/create">
        <label>Name: <input type="text" name="name" required></label><br><br>
        <label>Email: <input type="email" name="email" required></label><br><br>
        <button type="submit">Create</button>
    </form>
</body>
</html>
