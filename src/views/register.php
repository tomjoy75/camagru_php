<?php
$email = $email ?? '';
$username = $username ?? '';
$errors = $errors ?? [];
?>
<form method="post" action="/register">
    <?php if (isset($errors['form'])): ?>
        <p><?php echo htmlspecialchars($errors['form'], ENT_QUOTES, 'UTF-8'); ?></p>
    <?php endif; ?>
    <div>
        <label for="email">Email</label>
        <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($email, ENT_QUOTES, 'UTF-8'); ?>" required>
        <?php if (isset($errors['email'])): ?>
            <span><?php echo htmlspecialchars($errors['email'], ENT_QUOTES, 'UTF-8'); ?></span>
        <?php endif; ?>
    </div>
    <div>
        <label for="username">Username</label>
        <input type="text" id="username" name="username" value="<?php echo htmlspecialchars($username, ENT_QUOTES, 'UTF-8'); ?>" required>
        <?php if (isset($errors['username'])): ?>
            <span><?php echo htmlspecialchars($errors['username'], ENT_QUOTES, 'UTF-8'); ?></span>
        <?php endif; ?>
    </div>
    <div>
        <label for="password">Password</label>
        <input type="password" id="password" name="password" required>
        <?php if (isset($errors['password'])): ?>
            <span><?php echo htmlspecialchars($errors['password'], ENT_QUOTES, 'UTF-8'); ?></span>
        <?php endif; ?>
    </div>
    <div>
        <label for="confirm_password">Confirm password</label>
        <input type="password" id="confirm_password" name="confirm_password" required>
        <?php if (isset($errors['confirm_password'])): ?>
            <span><?php echo htmlspecialchars($errors['confirm_password'], ENT_QUOTES, 'UTF-8'); ?></span>
        <?php endif; ?>
    </div>
    <button type="submit">Register</button>
</form>
