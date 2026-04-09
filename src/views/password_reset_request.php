<?php
$email = $email ?? '';
?>
<div class="bg-white border border-slate-200 rounded-lg shadow-sm p-6 space-y-4">
    <h1 class="text-lg font-semibold text-slate-800">Reset password</h1>
    <p class="text-slate-600 text-sm">Enter the email address for your account. If it matches a verified account, we will send a reset link.</p>
    <form method="post" action="/password-reset" class="space-y-4">
        <div class="space-y-1">
            <label for="email" class="block text-sm font-medium text-slate-700">Email</label>
            <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($email, ENT_QUOTES, 'UTF-8'); ?>" required class="w-full rounded border border-slate-300 px-3 py-2 text-slate-800 focus:border-slate-500 focus:outline-none focus:ring-1 focus:ring-slate-500">
        </div>
        <button type="submit" class="w-full rounded bg-slate-800 px-4 py-2 text-white font-medium hover:bg-slate-700 focus:outline-none focus:ring-2 focus:ring-slate-500 focus:ring-offset-2">Send reset link</button>
    </form>
    <p class="text-sm text-slate-600"><a href="/login" class="text-slate-800 underline">Back to log in</a></p>
</div>
