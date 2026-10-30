<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" type="image/png" href="<?= base_url('assets/favicon.png') ?>">
    <link rel="apple-touch-icon" href="<?= base_url('assets/favicon.png') ?>">
    <title>KINTA Admin Login</title>
    <style>
        :root {
            --kinta-primary: #087cc1;
            --kinta-primary-hover: #055d93;
        }

        body {
            font: 16px system-ui;
            background: #f1f5f9;
            margin: 0;
            display: grid;
            min-height: 100vh;
            place-items: center;
        }

        .card {
            background: white;
            padding: 2rem;
            border-radius: 12px;
            box-shadow: 0 8px 30px #10203020;
            width: min(380px, 85vw);
        }

        input,
        button {
            box-sizing: border-box;
            width: 100%;
            padding: 12px;
            margin: 12px 0 18px;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            font: inherit;
        }

        label {
            display: block;
            margin: 8px 0;
        }

        button {
            background: var(--kinta-primary);
            color: white;
            font-weight: bold;
            border: 0;
            cursor: pointer;
            transition: background-color .15s ease;
        }

        button:hover,
        button:focus-visible {
            background: var(--kinta-primary-hover);
            color: white;
        }

        .error {
            color: #b91c1c;
        }
    </style>
</head>
<body>
    <form class="card" method="post" action="<?= site_url('admin/login') ?>">
        <h1>KINTA Admin</h1>

        <?php if ($error): ?>
            <p class="error"><?= esc($error) ?></p>
        <?php endif ?>

        <?= csrf_field() ?>

        <label>
            Email / username
            <input name="username" type="text" autocomplete="username" required>
        </label>
        <label>
            Password
            <input name="password" type="password" autocomplete="current-password" required>
        </label>

        <button>Sign in</button>
        <a href="#" onclick="alert('Password reset email is not configured yet. Contact your system administrator.');return false">
            Forgot password?
        </a>
    </form>
</body>
</html>
