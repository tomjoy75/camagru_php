<?php
$username = $username ?? '';
$email = $email ?? '';
$profileSettingsSuccess = $profileSettingsSuccess ?? null;
$profileSettingsError = $profileSettingsError ?? null;
?>
<div class="space-y-6">
    <div>
        <h1 class="text-2xl font-semibold text-slate-50">Profile settings</h1>
        <p class="mt-2 text-slate-400 text-sm">
            Update your username, email, or password.
        </p>
    </div>

    <?php if ($profileSettingsSuccess !== null): ?>
        <p class="text-emerald-400 text-sm"><?php echo htmlspecialchars($profileSettingsSuccess, ENT_QUOTES, 'UTF-8'); ?></p>
    <?php endif; ?>
    <?php if ($profileSettingsError !== null): ?>
        <p class="text-red-400 text-sm"><?php echo htmlspecialchars($profileSettingsError, ENT_QUOTES, 'UTF-8'); ?></p>
    <?php endif; ?>

    <form method="post" action="/settings/profile/username" class="bg-slate-900 border border-slate-700 rounded-lg shadow-lg shadow-black/20 p-6 space-y-4">
        <div class="space-y-1">
            <label for="username" class="block text-sm font-medium text-slate-300">Username</label>
            <input
                type="text"
                id="username"
                name="username"
                value="<?php echo htmlspecialchars($username, ENT_QUOTES, 'UTF-8'); ?>"
                required
                class="w-full rounded border border-slate-600 bg-slate-800 px-3 py-2 text-slate-100 focus:border-cyan-500 focus:outline-none focus:ring-1 focus:ring-cyan-500"
            >
        </div>
        <button type="submit" class="rounded bg-cyan-600 px-4 py-2 text-white text-sm font-medium hover:bg-cyan-500 focus:outline-none focus:ring-2 focus:ring-cyan-400 focus:ring-offset-2 focus:ring-offset-slate-900">Save username</button>
    </form>

    <form method="post" action="/settings/profile/email" class="bg-slate-900 border border-slate-700 rounded-lg shadow-lg shadow-black/20 p-6 space-y-4">
        <div class="space-y-1">
            <label for="email" class="block text-sm font-medium text-slate-300">Email</label>
            <input
                type="email"
                id="email"
                name="email"
                value="<?php echo htmlspecialchars($email, ENT_QUOTES, 'UTF-8'); ?>"
                required
                class="w-full rounded border border-slate-600 bg-slate-800 px-3 py-2 text-slate-100 focus:border-cyan-500 focus:outline-none focus:ring-1 focus:ring-cyan-500"
            >
        </div>
        <button type="submit" class="rounded bg-cyan-600 px-4 py-2 text-white text-sm font-medium hover:bg-cyan-500 focus:outline-none focus:ring-2 focus:ring-cyan-400 focus:ring-offset-2 focus:ring-offset-slate-900">Save email</button>
    </form>

    <form method="post" action="/settings/profile/password" class="bg-slate-900 border border-slate-700 rounded-lg shadow-lg shadow-black/20 p-6 space-y-4">
        <div class="space-y-1">
            <label for="current_password" class="block text-sm font-medium text-slate-300">Current password</label>
            <input
                type="password"
                id="current_password"
                name="current_password"
                required
                autocomplete="current-password"
                class="w-full rounded border border-slate-600 bg-slate-800 px-3 py-2 text-slate-100 focus:border-cyan-500 focus:outline-none focus:ring-1 focus:ring-cyan-500"
            >
        </div>
        <div class="space-y-1">
            <label for="password" class="block text-sm font-medium text-slate-300">New password</label>
            <input
                type="password"
                id="password"
                name="password"
                required
                minlength="8"
                autocomplete="new-password"
                class="w-full rounded border border-slate-600 bg-slate-800 px-3 py-2 text-slate-100 focus:border-cyan-500 focus:outline-none focus:ring-1 focus:ring-cyan-500"
            >
        </div>
        <div class="space-y-1">
            <label for="confirm_password" class="block text-sm font-medium text-slate-300">Confirm new password</label>
            <input
                type="password"
                id="confirm_password"
                name="confirm_password"
                required
                minlength="8"
                autocomplete="new-password"
                class="w-full rounded border border-slate-600 bg-slate-800 px-3 py-2 text-slate-100 focus:border-cyan-500 focus:outline-none focus:ring-1 focus:ring-cyan-500"
            >
        </div>
        <button type="submit" class="rounded bg-cyan-600 px-4 py-2 text-white text-sm font-medium hover:bg-cyan-500 focus:outline-none focus:ring-2 focus:ring-cyan-400 focus:ring-offset-2 focus:ring-offset-slate-900">Change password</button>
    </form>
</div>
