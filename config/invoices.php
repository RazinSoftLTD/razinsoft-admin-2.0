<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Billing address required from
    |--------------------------------------------------------------------------
    |
    | An invoice raised on or after this moment cannot be paid until it has a
    | billing address — the payer is asked for one on the payment link.
    |
    | Invoices raised before it are left alone. They were payable without an
    | address yesterday, and quietly freezing a bill somebody is about to settle
    | is not a change worth making to collect a field. The alternative — writing
    | an address onto an existing invoice — would mean inventing one, because
    | none of those clients has an address on file anywhere. An invented address
    | on a financial record is worse than a missing one: a blank says "we do not
    | know", and a made-up line says something untrue about who was billed.
    |
    | Set INVOICE_ADDRESS_REQUIRED_FROM to move the line, or to a past date to
    | apply the rule to everything.
    |
    */

    'billing_address_required_from' => env('INVOICE_ADDRESS_REQUIRED_FROM', '2026-07-29'),

];
