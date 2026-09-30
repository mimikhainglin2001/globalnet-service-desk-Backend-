<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Frontend
    |--------------------------------------------------------------------------
    |
    | Base URL of the React SPA. Used to build links in emails (e.g. the
    | password reset link) that must open the frontend, not the API.
    |
    */

    'frontend_url' => env('FRONTEND_URL', 'http://localhost:5173'),

    /*
    |--------------------------------------------------------------------------
    | Tickets
    |--------------------------------------------------------------------------
    */

    'tickets' => [
        'reference_prefix' => env('TICKET_REFERENCE_PREFIX', 'GN-'),
        'reference_padding' => (int) env('TICKET_REFERENCE_PADDING', 6),

        // Customers may reopen a resolved/closed ticket within this window.
        'reopen_window_days' => (int) env('TICKET_REOPEN_WINDOW_DAYS', 7),

        'default_per_page' => 15,
        'max_per_page' => 100,
    ],

    /*
    |--------------------------------------------------------------------------
    | SLA
    |--------------------------------------------------------------------------
    |
    | Used only when no active SLA rule exists for a priority. Admins manage
    | the real values in the sla_rules table.
    |
    */

    'sla' => [
        'fallback_hours' => [
            'low' => 72,
            'normal' => 24,
            'high' => 8,
            'urgent' => 4,
        ],
        'check_batch_size' => (int) env('SLA_CHECK_BATCH_SIZE', 100),
    ],

    /*
    |--------------------------------------------------------------------------
    | Attachments
    |--------------------------------------------------------------------------
    */

    'attachments' => [
        'disk' => env('ATTACHMENTS_DISK', 'local'),
        'directory' => 'attachments',
        'max_files' => (int) env('ATTACHMENTS_MAX_FILES', 3),
        'max_size_kb' => (int) env('ATTACHMENTS_MAX_SIZE_KB', 2048),
        'allowed_mimes' => ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'txt', 'doc', 'docx', 'xls', 'xlsx', 'csv', 'zip'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Idempotency
    |--------------------------------------------------------------------------
    */

    'idempotency' => [
        'header' => 'Idempotency-Key',
        'ttl_hours' => (int) env('IDEMPOTENCY_TTL_HOURS', 24),
        'max_key_length' => 255,
    ],

    /*
    |--------------------------------------------------------------------------
    | Transactional outbox
    |--------------------------------------------------------------------------
    */

    'outbox' => [
        'queue' => env('OUTBOX_QUEUE', 'default'),
        'tries' => (int) env('OUTBOX_TRIES', 5),
        'backoff_seconds' => [10, 30, 60, 120],
        'relay_batch_size' => (int) env('OUTBOX_RELAY_BATCH_SIZE', 100),

        // The relay only re-dispatches events older than this, so it does not
        // race the after-commit dispatch of freshly written events.
        'relay_grace_seconds' => (int) env('OUTBOX_RELAY_GRACE_SECONDS', 60),
    ],

    /*
    |--------------------------------------------------------------------------
    | Rate limits (requests per minute)
    |--------------------------------------------------------------------------
    */

    'rate_limits' => [
        'auth' => (int) env('RATE_LIMIT_AUTH', 5),
        'password_reset' => (int) env('RATE_LIMIT_PASSWORD_RESET', 3),
        'api' => (int) env('RATE_LIMIT_API', 120),
    ],

    'dashboard' => [
        'resolved_window_days' => 7,
    ],

    // Shared password of the seeded demo accounts.
    'demo_password' => env('DEMO_USER_PASSWORD', 'Password123!'),
];
