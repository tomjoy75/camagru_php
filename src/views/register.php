<?php
$email = $email ?? '';
$username = $username ?? '';
$errors = $errors ?? [];
?>
<form method="post" action="/register" class="bg-slate-900 border border-slate-700 rounded-lg shadow-lg shadow-black/20 p-6 space-y-4">
    <?php if (isset($errors['form'])): ?>
        <p class="text-red-400 text-sm"><?php echo htmlspecialchars($errors['form'], ENT_QUOTES, 'UTF-8'); ?></p>
    <?php endif; ?>
    <div class="space-y-1">
        <label for="email" class="block text-sm font-medium text-slate-300">Email</label>
        <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($email, ENT_QUOTES, 'UTF-8'); ?>" required class="w-full rounded border border-slate-600 bg-slate-800 px-3 py-2 text-slate-100 focus:border-cyan-500 focus:outline-none focus:ring-1 focus:ring-cyan-500">
        <?php if (isset($errors['email'])): ?>
            <span class="text-red-400 text-sm"><?php echo htmlspecialchars($errors['email'], ENT_QUOTES, 'UTF-8'); ?></span>
        <?php endif; ?>
    </div>
    <div class="space-y-1">
        <label for="username" class="block text-sm font-medium text-slate-300">Username</label>
        <input type="text" id="username" name="username" value="<?php echo htmlspecialchars($username, ENT_QUOTES, 'UTF-8'); ?>" required class="w-full rounded border border-slate-600 bg-slate-800 px-3 py-2 text-slate-100 focus:border-cyan-500 focus:outline-none focus:ring-1 focus:ring-cyan-500">
        <?php if (isset($errors['username'])): ?>
            <span class="text-red-400 text-sm"><?php echo htmlspecialchars($errors['username'], ENT_QUOTES, 'UTF-8'); ?></span>
        <?php endif; ?>
    </div>
    <div class="space-y-1">
        <label for="password" class="block text-sm font-medium text-slate-300">Password</label>
        <input type="password" id="password" name="password" required class="w-full rounded border border-slate-600 bg-slate-800 px-3 py-2 text-slate-100 focus:border-cyan-500 focus:outline-none focus:ring-1 focus:ring-cyan-500">
        <?php if (isset($errors['password'])): ?>
            <span class="text-red-400 text-sm"><?php echo htmlspecialchars($errors['password'], ENT_QUOTES, 'UTF-8'); ?></span>
        <?php endif; ?>
    </div>
    <div class="space-y-1">
        <label for="confirm_password" class="block text-sm font-medium text-slate-300">Confirm password</label>
        <input type="password" id="confirm_password" name="confirm_password" required class="w-full rounded border border-slate-600 bg-slate-800 px-3 py-2 text-slate-100 focus:border-cyan-500 focus:outline-none focus:ring-1 focus:ring-cyan-500">
        <?php if (isset($errors['confirm_password'])): ?>
            <span class="text-red-400 text-sm"><?php echo htmlspecialchars($errors['confirm_password'], ENT_QUOTES, 'UTF-8'); ?></span>
        <?php endif; ?>
    </div>
    <button type="submit" class="w-full rounded bg-cyan-600 px-4 py-2 text-white font-medium hover:bg-cyan-500 focus:outline-none focus:ring-2 focus:ring-cyan-400 focus:ring-offset-2 focus:ring-offset-slate-900">Register</button>
</form>
