<?php
$confirmResult = $confirmResult ?? 'invalid';
?>
<div class="bg-slate-900 border border-slate-700 rounded-lg shadow-lg shadow-black/20 p-6 space-y-3">
    <?php if ($confirmResult === 'success'): ?>
        <h1 class="text-lg font-semibold text-slate-100">Email confirmed</h1>
        <p class="text-slate-300 text-sm">Your account is verified. You can <a href="/login" class="text-cyan-400 underline hover:text-cyan-300">log in</a>.</p>
    <?php elseif ($confirmResult === 'already'): ?>
        <h1 class="text-lg font-semibold text-slate-100">Already confirmed</h1>
        <p class="text-slate-300 text-sm">This account was already verified. You can <a href="/login" class="text-cyan-400 underline hover:text-cyan-300">log in</a>.</p>
    <?php else: ?>
        <h1 class="text-lg font-semibold text-slate-100">Invalid confirmation link</h1>
        <p class="text-slate-300 text-sm">This link is invalid or has expired. If you need help, register again or log in if you already have an account.</p>
        <p class="text-sm"><a href="/register" class="text-cyan-400 underline hover:text-cyan-300">Register</a> <span class="text-slate-600">·</span> <a href="/login" class="text-cyan-400 underline hover:text-cyan-300">Log in</a></p>
    <?php endif; ?>
</div>
