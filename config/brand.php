<?php

/*
|--------------------------------------------------------------------------
| Brand details used in outgoing email
|--------------------------------------------------------------------------
|
| The name and logo come from Invoice Configuration, because that is where the
| brand is already maintained. What lives here is everything else the email
| footer needs — the same links and address the public website footer shows,
| kept in one place so they cannot drift apart.
|
| Source of truth: razinsoft-website-2.0/components/AppFooter.vue
|
*/

return [

    'website' => env('BRAND_WEBSITE', 'https://www.razinsoft.com'),

    'support_email' => env('BRAND_SUPPORT_EMAIL', 'support@razinsoft.com'),

    'address' => env('BRAND_ADDRESS', '1st Floor, RMR Center, A&B Ring Rd, Dhaka 1207'),

    /*
     | Order matters: it is the order the icons appear in the footer.
     | 'icon' is the file name under public/images/email/social/.
     */
    'social' => [
        'facebook' => ['label' => 'Facebook', 'url' => 'https://www.facebook.com/razinsoft'],
        'instagram' => ['label' => 'Instagram', 'url' => 'https://instagram.com/razinsoftltd'],
        'linkedin' => ['label' => 'LinkedIn', 'url' => 'https://www.linkedin.com/company/razinsoft/'],
        'youtube' => ['label' => 'YouTube', 'url' => 'https://www.youtube.com/@razinsoft'],
    ],

];
