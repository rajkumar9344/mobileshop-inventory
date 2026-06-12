<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class AuditLogger
{
    /**
     * Write a security-relevant action to the dedicated audit log channel.
     *
     * Never pass passwords, tokens, or other secrets in $context.
     *
     * @param string $action  e.g. 'user.created', 'role.updated', 'password.changed'
     * @param array  $context extra details (record ids, names) — no PII beyond what's needed
     */
    public static function log(string $action, array $context = []): void
    {
        Log::channel('audit')->info($action, array_merge([
            'actor_id'    => auth()->id(),
            'actor_email' => auth()->user()->email ?? null,
            'ip'          => request()->ip(),
        ], $context));
    }
}
