<?php
$notificationsEnabled = $notificationsEnabled ?? 1;
$notificationsSettingsSuccess = $notificationsSettingsSuccess ?? null;
$notificationsSettingsError = $notificationsSettingsError ?? null;
?>
<div class="space-y-6">
    <div>
        <h1 class="text-2xl font-semibold text-slate-900">Notification settings</h1>
        <p class="mt-2 text-slate-600 text-sm">
            Choose whether you want to be notified (e.g. by email when someone comments on your images). This does not send mail yet; it only stores your preference for future use.
        </p>
    </div>

    <?php if ($notificationsSettingsSuccess !== null): ?>
        <p class="text-green-700 text-sm"><?php echo htmlspecialchars($notificationsSettingsSuccess, ENT_QUOTES, 'UTF-8'); ?></p>
    <?php endif; ?>
    <?php if ($notificationsSettingsError !== null): ?>
        <p class="text-red-600 text-sm"><?php echo htmlspecialchars($notificationsSettingsError, ENT_QUOTES, 'UTF-8'); ?></p>
    <?php endif; ?>

    <form method="post" action="/settings/notifications" class="bg-white border border-slate-200 rounded-lg shadow-sm p-6 space-y-4">
        <fieldset>
            <legend class="text-sm font-medium text-slate-700">Comment notifications</legend>
            <div class="mt-3 space-y-2">
                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="radio" name="notifications_enabled" value="1" <?php echo $notificationsEnabled === 1 ? 'checked' : ''; ?> class="text-slate-800 focus:ring-slate-500">
                    <span class="text-slate-800">On</span>
                </label>
                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="radio" name="notifications_enabled" value="0" <?php echo $notificationsEnabled === 0 ? 'checked' : ''; ?> class="text-slate-800 focus:ring-slate-500">
                    <span class="text-slate-800">Off</span>
                </label>
            </div>
        </fieldset>
        <button type="submit" class="rounded bg-slate-800 px-4 py-2 text-white text-sm font-medium hover:bg-slate-700 focus:outline-none focus:ring-2 focus:ring-slate-500 focus:ring-offset-2">Save</button>
    </form>
</div>
