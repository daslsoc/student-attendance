<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Student-registration API
    |--------------------------------------------------------------------------
    |
    | Where the sibling student-registration app lives and the shared bearer
    | token to authenticate to it. The token must match INTEGRATION_API_TOKEN
    | on the registration side. In production the URL must be HTTPS.
    |
    | Dev: the two Docker stacks are on separate networks, so set the URL to a
    | reachable address (see docs/integration.md).
    |
    */

    'registration_url' => env('REGISTRATION_API_URL'),

    'registration_token' => env('REGISTRATION_API_TOKEN'),

    /*
    |--------------------------------------------------------------------------
    | Subject mapping
    |--------------------------------------------------------------------------
    |
    | Registration records each child's class per subject as `dhamma_class` and
    | `sinhala_class`. These map to Subject rows here by name. If your subjects
    | are named differently (e.g. "Dhamma"), override these so the importer can
    | find them. The class value itself is matched to a ClassModel by name.
    |
    */

    'subject_for_dhamma' => env('INTEGRATION_DHAMMA_SUBJECT', 'Buddhism'),

    'subject_for_sinhala' => env('INTEGRATION_SINHALA_SUBJECT', 'Sinhala'),

    /*
    | Registration's sentinel value meaning "no class for this subject" — these
    | children should NOT be enrolled in that subject.
    */

    'no_class_value' => 'Did not attend last year',

];
