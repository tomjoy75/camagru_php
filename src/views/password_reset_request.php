<?php
$email = $email ?? '';
?>
<div class="bg-slate-900 border border-slate-700 rounded-lg shadow-lg shadow-black/20 p-6 space-y-4">
    <h1 class="text-lg font-semibold text-slate-100">Reset password</h1>
    <p class="text-slate-300 text-sm">Enter the email address for your account. If it matches a verified account, we will send a reset link.</p>
    <form method="post" action="/password-reset" class="space-y-4">
        <div class="space-y-1">
            <label for="email" class="block text-sm font-medium text-slate-300">Email</label>
            <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($email, ENT_QUOTES, 'UTF-8'); ?>" required class="w-full rounded border border-slate-600 bg-slate-800 px-3 py-2 text-slate-100 focus:border-cyan-500 focus:outline-none focus:ring-1 focus:ring-cyan-500">
        </div>
        <button type="submit" class="w-full rounded bg-cyan-600 px-4 py-2 text-white font-medium hover:bg-cyan-500 focus:outline-none focus:ring-2 focus:ring-cyan-400 focus:ring-offset-2 focus:ring-offset-slate-900">Send reset link</button>
    </form>
    <p class="text-sm text-slate-400"><a href="/login" class="text-cyan-400 underline hover:text-cyan-300">Back to log in</a></p>
</div>
