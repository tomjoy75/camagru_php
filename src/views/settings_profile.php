<?php
$username = $username ?? '';
$email = $email ?? '';
$profileSettingsSuccess = $profileSettingsSuccess ?? null;
$profileSettingsError = $profileSettingsError ?? null;
?>
<div class="space-y-6">
    <div>
        <h1 class="text-2xl font-semibold text-slate-900">Profile settings</h1>
        <p class="mt-2 text-slate-600 text-sm">
            Update your username and email. Password changes are handled in a separate feature.
        </p>
    </div>

    <?php if ($profileSettingsSuccess !== null): ?>
        <p class="text-green-700 text-sm"><?php echo htmlspecialchars($profileSettingsSuccess, ENT_QUOTES, 'UTF-8'); ?></p>
    <?php endif; ?>
    <?php if ($profileSettingsError !== null): ?>
        <p class="text-red-600 text-sm"><?php echo htmlspecialchars($profileSettingsError, ENT_QUOTES, 'UTF-8'); ?></p>
    <?php endif; ?>

    <form method="post" action="/settings/profile/username" class="bg-white border border-slate-200 rounded-lg shadow-sm p-6 space-y-4">
        <div class="space-y-1">
            <label for="username" class="block text-sm font-medium text-slate-700">Username</label>
            <input
                type="text"
                id="username"
                name="username"
                value="<?php echo htmlspecialchars($username, ENT_QUOTES, 'UTF-8'); ?>"
                required
                class="w-full rounded border border-slate-300 px-3 py-2 text-slate-800 focus:border-slate-500 focus:outline-none focus:ring-1 focus:ring-slate-500"
            >
        </div>
        <button type="submit" class="rounded bg-slate-800 px-4 py-2 text-white text-sm font-medium hover:bg-slate-700 focus:outline-none focus:ring-2 focus:ring-slate-500 focus:ring-offset-2">Save username</button>
    </form>

    <form method="post" action="/settings/profile/email" class="bg-white border border-slate-200 rounded-lg shadow-sm p-6 space-y-4">
        <div class="space-y-1">
            <label for="email" class="block text-sm font-medium text-slate-700">Email</label>
            <input
                type="email"
                id="email"
                name="email"
                value="<?php echo htmlspecialchars($email, ENT_QUOTES, 'UTF-8'); ?>"
                required
                class="w-full rounded border border-slate-300 px-3 py-2 text-slate-800 focus:border-slate-500 focus:outline-none focus:ring-1 focus:ring-slate-500"
            >
        </div>
        <button type="submit" class="rounded bg-slate-800 px-4 py-2 text-white text-sm font-medium hover:bg-slate-700 focus:outline-none focus:ring-2 focus:ring-slate-500 focus:ring-offset-2">Save email</button>
    </form>
</div>
