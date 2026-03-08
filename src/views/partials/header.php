<header>
    <p>Camagru</p>
    <?php if (isset($_SESSION['user_id'])): ?>
        <a href="/logout">Logout</a>
    <?php else: ?>
        <a href="/login">Login</a> | <a href="/register">Register</a>
    <?php endif; ?>
</header>
