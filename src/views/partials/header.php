<header class="max-w-2xl mx-auto px-4 py-4 flex items-center justify-between">
    <p class="font-semibold text-lg text-slate-800">Camagru</p>
    <nav class="flex items-center gap-4">
        <a href="/gallery" class="text-slate-600 hover:text-slate-900 underline">Gallery</a>
        <a href="/editor" class="text-slate-600 hover:text-slate-900 underline">Editor</a>
        <?php if (isset($_SESSION['user_id']) && $_SESSION['user_id'] !== ''): ?>
            <a href="/settings/profile" class="text-slate-600 hover:text-slate-900 underline">Profile</a>
            <a href="/settings/notifications" class="text-slate-600 hover:text-slate-900 underline">Notifications</a>
            <a href="/logout" class="text-slate-600 hover:text-slate-900 underline">Logout</a>
        <?php else: ?>
            <a href="/login" class="text-slate-600 hover:text-slate-900 underline">Login</a>
            <span class="text-slate-400">|</span>
            <a href="/register" class="text-slate-600 hover:text-slate-900 underline">Register</a>
        <?php endif; ?>
    </nav>
</header>
