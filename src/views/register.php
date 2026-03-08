<?php
$email = $email ?? '';
$username = $username ?? '';
$errors = $errors ?? [];
?>
<form method="post" action="/register" class="bg-white border border-slate-200 rounded-lg shadow-sm p-6 space-y-4">
    <?php if (isset($errors['form'])): ?>
        <p class="text-red-600 text-sm"><?php echo htmlspecialchars($errors['form'], ENT_QUOTES, 'UTF-8'); ?></p>
    <?php endif; ?>
    <div class="space-y-1">
        <label for="email" class="block text-sm font-medium text-slate-700">Email</label>
        <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($email, ENT_QUOTES, 'UTF-8'); ?>" required class="w-full rounded border border-slate-300 px-3 py-2 text-slate-800 focus:border-slate-500 focus:outline-none focus:ring-1 focus:ring-slate-500">
        <?php if (isset($errors['email'])): ?>
            <span class="text-red-600 text-sm"><?php echo htmlspecialchars($errors['email'], ENT_QUOTES, 'UTF-8'); ?></span>
        <?php endif; ?>
    </div>
    <div class="space-y-1">
        <label for="username" class="block text-sm font-medium text-slate-700">Username</label>
        <input type="text" id="username" name="username" value="<?php echo htmlspecialchars($username, ENT_QUOTES, 'UTF-8'); ?>" required class="w-full rounded border border-slate-300 px-3 py-2 text-slate-800 focus:border-slate-500 focus:outline-none focus:ring-1 focus:ring-slate-500">
        <?php if (isset($errors['username'])): ?>
            <span class="text-red-600 text-sm"><?php echo htmlspecialchars($errors['username'], ENT_QUOTES, 'UTF-8'); ?></span>
        <?php endif; ?>
    </div>
    <div class="space-y-1">
        <label for="password" class="block text-sm font-medium text-slate-700">Password</label>
        <input type="password" id="password" name="password" required class="w-full rounded border border-slate-300 px-3 py-2 text-slate-800 focus:border-slate-500 focus:outline-none focus:ring-1 focus:ring-slate-500">
        <?php if (isset($errors['password'])): ?>
            <span class="text-red-600 text-sm"><?php echo htmlspecialchars($errors['password'], ENT_QUOTES, 'UTF-8'); ?></span>
        <?php endif; ?>
    </div>
    <div class="space-y-1">
        <label for="confirm_password" class="block text-sm font-medium text-slate-700">Confirm password</label>
        <input type="password" id="confirm_password" name="confirm_password" required class="w-full rounded border border-slate-300 px-3 py-2 text-slate-800 focus:border-slate-500 focus:outline-none focus:ring-1 focus:ring-slate-500">
        <?php if (isset($errors['confirm_password'])): ?>
            <span class="text-red-600 text-sm"><?php echo htmlspecialchars($errors['confirm_password'], ENT_QUOTES, 'UTF-8'); ?></span>
        <?php endif; ?>
    </div>
    <button type="submit" class="w-full rounded bg-slate-800 px-4 py-2 text-white font-medium hover:bg-slate-700 focus:outline-none focus:ring-2 focus:ring-slate-500 focus:ring-offset-2">Register</button>
</form>
