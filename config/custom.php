<?php

return [
    'management_team_name' => env('MANAGEMENT_TEAM_NAME', 'Dhamma and Sinhala Management Team'),

    // How long a magic login link stays valid, in hours. Pinned under test
    // via phpunit.xml so token-expiry assertions are deterministic.
    'token_expiry_hours' => (int) env('TOKEN_EXPIRY_HOURS', 4),
];
