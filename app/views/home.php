<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PointStart</title>
    <style>
        body { font-family: sans-serif; max-width: 800px; margin: 80px auto; padding: 0 20px; text-align: center; }
        h1 { font-size: 2.5em; margin-bottom: 0.2em; }
        p { color: #666; font-size: 1.1em; margin-bottom: 40px; }
        .cards { display: flex; gap: 24px; justify-content: center; }
        .card {
            flex: 1; max-width: 300px; padding: 32px 24px;
            border: 1px solid #ddd; border-radius: 8px;
            text-decoration: none; color: inherit;
            transition: box-shadow 0.2s, transform 0.2s;
        }
        .card:hover { box-shadow: 0 4px 16px rgba(0,0,0,0.1); transform: translateY(-2px); }
        .card h2 { margin: 0 0 8px; font-size: 1.4em; }
        .card span { color: #666; font-size: 0.95em; }
        .links { margin-top: 48px; }
        .links a { color: #0066cc; text-decoration: none; margin: 0 12px; font-size: 0.95em; }
        .links a:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <h1>PointArt</h1>

    <div class="links">
        <a href="https://pointartframework.com">Documentation</a>
        <a href="https://pointartframework.com">Getting Started</a>
    </div>
    <p>Welcome to PointStart, a simple PHP framework for building web applications. Below are example apps.</p>
    <div class="cards">
        <a href="/user/list" class="card">
            <h2>Users</h2>
            <span>Manage users &mdash; list, create, edit, delete</span>
        </a>
        <a href="/product/list" class="card">
            <h2>Products</h2>
            <span>Manage products &mdash; inventory, pricing, stock</span>
        </a>
    </div>

</body>
</html>
