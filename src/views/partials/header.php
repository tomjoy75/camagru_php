<header class="max-w-2xl mx-auto px-4 py-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
    <p class="font-semibold text-lg text-slate-100 tracking-tight">Camagru</p>
    <nav class="flex flex-wrap items-center gap-3">
        <a href="/gallery" class="text-slate-400 hover:text-cyan-400 underline underline-offset-2 rounded px-0.5 focus:outline-none focus:ring-2 focus:ring-cyan-500 focus:ring-offset-2 focus:ring-offset-slate-900">Gallery</a>
        <a href="/editor" class="text-slate-400 hover:text-cyan-400 underline underline-offset-2 rounded px-0.5 focus:outline-none focus:ring-2 focus:ring-cyan-500 focus:ring-offset-2 focus:ring-offset-slate-900">Editor</a>
        <?php if (isset($_SESSION['user_id']) && $_SESSION['user_id'] !== ''): ?>
            <a href="/settings/profile" class="text-slate-400 hover:text-cyan-400 underline underline-offset-2 rounded px-0.5 focus:outline-none focus:ring-2 focus:ring-cyan-500 focus:ring-offset-2 focus:ring-offset-slate-900">Profile</a>
            <a href="/settings/notifications" class="text-slate-400 hover:text-cyan-400 underline underline-offset-2 rounded px-0.5 focus:outline-none focus:ring-2 focus:ring-cyan-500 focus:ring-offset-2 focus:ring-offset-slate-900">Notifications</a>
            <a href="/logout" class="text-slate-400 hover:text-cyan-400 underline underline-offset-2 rounded px-0.5 focus:outline-none focus:ring-2 focus:ring-cyan-500 focus:ring-offset-2 focus:ring-offset-slate-900">Logout</a>
        <?php else: ?>
            <a href="/login" class="text-slate-400 hover:text-cyan-400 underline underline-offset-2 rounded px-0.5 focus:outline-none focus:ring-2 focus:ring-cyan-500 focus:ring-offset-2 focus:ring-offset-slate-900">Login</a>
            <span class="text-slate-600" aria-hidden="true">|</span>
            <a href="/register" class="text-slate-400 hover:text-cyan-400 underline underline-offset-2 rounded px-0.5 focus:outline-none focus:ring-2 focus:ring-cyan-500 focus:ring-offset-2 focus:ring-offset-slate-900">Register</a>
        <?php endif; ?>
    </nav>
</header>
