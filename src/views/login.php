<?php
$username = $username ?? '';
$errors = $errors ?? [];
?>
<form method="post" action="/login" class="bg-white border border-slate-200 rounded-lg shadow-sm p-6 space-y-4">
    <?php if (isset($errors['email_verification'])): ?>
        <p class="text-red-600 text-sm"><?php echo htmlspecialchars($errors['email_verification'], ENT_QUOTES, 'UTF-8'); ?></p>
    <?php endif; ?>
    <?php if (isset($errors['form'])): ?>
        <p class="text-red-600 text-sm"><?php echo htmlspecialchars($errors['form'], ENT_QUOTES, 'UTF-8'); ?></p>
    <?php endif; ?>
    <div class="space-y-1">
        <label for="username" class="block text-sm font-medium text-slate-700">Username</label>
        <input type="text" id="username" name="username" value="<?php echo htmlspecialchars($username, ENT_QUOTES, 'UTF-8'); ?>" required autocomplete="username" class="w-full rounded border border-slate-300 px-3 py-2 text-slate-800 focus:border-slate-500 focus:outline-none focus:ring-1 focus:ring-slate-500">
        <p class="text-right text-sm"><a href="/password-reset" class="text-slate-600 underline hover:text-slate-800">Forgot password?</a></p>
    </div>
    <div class="space-y-1">
        <label for="password" class="block text-sm font-medium text-slate-700">Password</label>
        <input type="password" id="password" name="password" required autocomplete="current-password" class="w-full rounded border border-slate-300 px-3 py-2 text-slate-800 focus:border-slate-500 focus:outline-none focus:ring-1 focus:ring-slate-500">
    </div>
    <button type="submit" class="w-full rounded bg-slate-800 px-4 py-2 text-white font-medium hover:bg-slate-700 focus:outline-none focus:ring-2 focus:ring-slate-500 focus:ring-offset-2">Login</button>
</form>
