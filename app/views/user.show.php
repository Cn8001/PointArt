<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($user->name) ?></title>
    <style>
        body { font-family: sans-serif; max-width: 600px; margin: 40px auto; padding: 0 20px; }
        label { display: block; margin-bottom: 12px; }
        input { width: 100%; padding: 6px; box-sizing: border-box; }
        button { cursor: pointer; margin-right: 8px; }
        a { color: #0066cc; text-decoration: none; }
    </style>
</head>
<body>
    <p><a href="/user/list">&larr; All users</a></p>
    <h1><?= htmlspecialchars($user->name) ?></h1>
    <p>ID: <?= $user->id ?> &nbsp;|&nbsp; Email: <?= htmlspecialchars($user->email) ?></p>

    <h2>Edit</h2>
    <form method="POST" action="/user/update/<?= $user->id ?>">
        <label>Name: <input type="text" name="name" value="<?= htmlspecialchars($user->name) ?>" required></label>
        <label>Email: <input type="email" name="email" value="<?= htmlspecialchars($user->email) ?>" required></label>
        <button type="submit">Save</button>
        <a href="/user/list">Cancel</a>
    </form>

    <hr>
    <form method="POST" action="/user/delete/<?= $user->id ?>">
        <button type="submit" onclick="return confirm('Delete <?= htmlspecialchars($user->name) ?>?')">Delete user</button>
    </form>
</body>
</html>
