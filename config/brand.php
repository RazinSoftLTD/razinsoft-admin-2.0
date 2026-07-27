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

    /*
     | The product itself. Everything a buyer sees — the sign-in screen, the sidebar, page titles,
     | the licence certificate — reads from here, so renaming the product is one line rather than a
     | search across thirty files.
     |
     | Note the difference from InvoiceSetting::brand_name: that is *the operator's* company, the
     | one whose name goes on the invoices they send. This is the software.
     */
    'product' => env('BRAND_PRODUCT', 'SmartDesk'),

    'tagline' => env('BRAND_TAGLINE', 'The business hub for growing teams'),

    /*
     | The marks, relative to public/. A buyer drops their own files in and points these at them;
     | nothing else has to change.
     */
    'icon' => env('BRAND_ICON', 'images/smartdesk-icon.svg'),
    'logo' => env('BRAND_LOGO', 'images/smartdesk-logo.svg'),

    /* Who makes it. Shown as "SmartDesk by RazinSoft". */
    'vendor' => env('BRAND_VENDOR', 'RazinSoft'),
    'vendor_url' => env('BRAND_VENDOR_URL', 'https://www.razinsoft.com'),

    'website' => env('BRAND_WEBSITE', 'https://www.razinsoft.com'),

    'support_email' => env('BRAND_SUPPORT_EMAIL', 'support@razinsoft.com'),

    /*
     | Where a *customer* signs in. Deliberately not APP_URL: that is the staff panel, which has
     | no customer login, so a welcome mail pointing at it lands on a 404.
     */
    'login_url' => env('BRAND_LOGIN_URL', 'https://www.razinsoft.com/login'),

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
