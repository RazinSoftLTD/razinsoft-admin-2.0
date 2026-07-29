<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Demo mode
    |--------------------------------------------------------------------------
    |
    | Turns on the sign-in credentials box on the admin login screen, so anyone
    | visiting the public demo can get in without being handed a password.
    |
    | Off unless DEMO_MODE is explicitly true. That default is the whole safety
    | of this feature: an install that never heard of this setting — which is
    | every real one — prints nothing. Nothing infers "this looks like a demo"
    | from the data, because a wrong guess publishes an admin password.
    |
    */

    'enabled' => filter_var(env('DEMO_MODE', false), FILTER_VALIDATE_BOOL),

    /*
    | The account shown. Matches what smartdesk:demo-seed creates. Kept in config
    | rather than in the view so a demo can point at a different account without
    | a code change, and so the values are visible to anyone auditing what the
    | login page might print.
    */

    'email' => env('DEMO_EMAIL', 'ariana@smartdesk.example'),

    'password' => env('DEMO_PASSWORD', 'demo1234'),

];
