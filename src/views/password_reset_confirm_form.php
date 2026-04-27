<?php
$resetToken = $resetToken ?? '';
$errors = $errors ?? [];
?>
<div class="bg-slate-900 border border-slate-700 rounded-lg shadow-lg shadow-black/20 p-6 space-y-4">
    <h1 class="text-lg font-semibold text-slate-100">Choose a new password</h1>
    <p class="text-slate-300 text-sm">Enter and confirm your new password below.</p>
    <?php if (isset($errors['form'])): ?>
        <p class="text-red-400 text-sm"><?php echo htmlspecialchars($errors['form'], ENT_QUOTES, 'UTF-8'); ?></p>
    <?php endif; ?>
    <form method="post" action="/password-reset/confirm" class="space-y-4">
        <input type="hidden" name="token" value="<?php echo htmlspecialchars($resetToken, ENT_QUOTES, 'UTF-8'); ?>">
        <div class="space-y-1">
            <label for="password" class="block text-sm font-medium text-slate-300">New password</label>
            <input type="password" id="password" name="password" required autocomplete="new-password" class="w-full rounded border border-slate-600 bg-slate-800 px-3 py-2 text-slate-100 focus:border-cyan-500 focus:outline-none focus:ring-1 focus:ring-cyan-500">
            <?php if (isset($errors['password'])): ?>
                <span class="text-red-400 text-sm"><?php echo htmlspecialchars($errors['password'], ENT_QUOTES, 'UTF-8'); ?></span>
            <?php endif; ?>
        </div>
        <div class="space-y-1">
            <label for="confirm_password" class="block text-sm font-medium text-slate-300">Confirm new password</label>
            <input type="password" id="confirm_password" name="confirm_password" required autocomplete="new-password" class="w-full rounded border border-slate-600 bg-slate-800 px-3 py-2 text-slate-100 focus:border-cyan-500 focus:outline-none focus:ring-1 focus:ring-cyan-500">
            <?php if (isset($errors['confirm_password'])): ?>
                <span class="text-red-400 text-sm"><?php echo htmlspecialchars($errors['confirm_password'], ENT_QUOTES, 'UTF-8'); ?></span>
            <?php endif; ?>
        </div>
        <button type="submit" class="w-full rounded bg-cyan-600 px-4 py-2 text-white font-medium hover:bg-cyan-500 focus:outline-none focus:ring-2 focus:ring-cyan-400 focus:ring-offset-2 focus:ring-offset-slate-900">Update password</button>
    </form>
    <p class="text-sm text-slate-400"><a href="/login" class="text-cyan-400 underline hover:text-cyan-300">Back to log in</a></p>
</div>
