<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Rollout administrator
    |--------------------------------------------------------------------------
    |
    | Read ONCE, by the migration that introduces roles, to decide which
    | existing teacher becomes the Administrator. Every other account lands on
    | the default role. Set ROLE_ROLLOUT_ADMIN_EMAIL in .env before migrating.
    |
    | Leave it blank (or name an address with no account) and the migration
    | promotes the OLDEST account instead, logging a warning. That fallback
    | exists so a deployment is never left with nobody able to manage teachers —
    | it is not a substitute for setting this.
    |
    | Changing this afterwards does nothing: once roles exist, accounts are
    | promoted from Admin -> Teachers in the app.
    |
    */
    'rollout_admin_email' => env('ROLE_ROLLOUT_ADMIN_EMAIL'),
];
