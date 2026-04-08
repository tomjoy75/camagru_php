<?php
$confirmResult = $confirmResult ?? 'invalid';
?>
<div class="bg-white border border-slate-200 rounded-lg shadow-sm p-6 space-y-3">
    <?php if ($confirmResult === 'success'): ?>
        <h1 class="text-lg font-semibold text-slate-800">Email confirmed</h1>
        <p class="text-slate-600 text-sm">Your account is verified. You can <a href="/login" class="text-slate-800 underline">log in</a>.</p>
    <?php elseif ($confirmResult === 'already'): ?>
        <h1 class="text-lg font-semibold text-slate-800">Already confirmed</h1>
        <p class="text-slate-600 text-sm">This account was already verified. You can <a href="/login" class="text-slate-800 underline">log in</a>.</p>
    <?php else: ?>
        <h1 class="text-lg font-semibold text-slate-800">Invalid confirmation link</h1>
        <p class="text-slate-600 text-sm">This link is invalid or has expired. If you need help, register again or log in if you already have an account.</p>
        <p class="text-sm"><a href="/register" class="text-slate-800 underline">Register</a> · <a href="/login" class="text-slate-800 underline">Log in</a></p>
    <?php endif; ?>
</div>
