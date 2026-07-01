<?php

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->products() as $p) {
            $product = Product::updateOrCreate(['slug' => $p['slug']], collect($p)->except([
                'plans', 'gallery', 'features', 'tech', 'suitable', 'docs', 'faqs',
            ])->toArray());

            // Demo / download links (admin can override in Filament → Product).
            $appId = 'com.razinsoft.'.str_replace('-', '', $p['slug']);
            $product->update([
                'live_demo_url' => "https://demo.razinsoft.com/{$p['slug']}",
                'admin_demo_url' => "https://demo.razinsoft.com/{$p['slug']}/admin",
                'customer_demo_url' => "https://demo.razinsoft.com/{$p['slug']}",
                'android_apk_url' => "https://play.google.com/store/apps/details?id={$appId}",
                'ios_ipa_url' => "https://apps.apple.com/app/{$p['slug']}",
            ]);

            // wipe children (idempotent reseed)
            $product->plans()->delete();
            $product->galleryGroups()->each(fn ($g) => $g->images()->delete());
            $product->galleryGroups()->delete();
            $product->features()->delete();
            $product->tech()->delete();
            $product->suitableFor()->delete();
            $product->docs()->delete();
            $product->faqs()->delete();
            $product->files()->delete();

            foreach ($p['plans'] as $i => $pl) {
                $product->plans()->create($pl + ['sort_order' => $i]);
            }
            foreach ($p['gallery'] as $gi => $grp) {
                $group = $product->galleryGroups()->create(['name' => $grp['name'], 'sort_order' => $gi]);
                foreach ($grp['images'] as $ii => $img) {
                    $group->images()->create(['image' => $img['image'], 'caption' => $img['caption'] ?? null, 'sort_order' => $ii]);
                }
            }
            foreach ($this->features() as $i => $f) {
                $product->features()->create($f + ['sort_order' => $i]);
            }
            foreach ($this->tech() as $i => $t) {
                $product->tech()->create($t + ['sort_order' => $i]);
            }
            foreach ($this->suitable() as $i => $s) {
                $product->suitableFor()->create(['label' => $s, 'sort_order' => $i]);
            }
            foreach ($this->docs() as $i => $d) {
                $product->docs()->create($d + ['sort_order' => $i]);
            }
            foreach ($this->faqs() as $i => $q) {
                $product->faqs()->create($q + ['sort_order' => $i]);
            }
            $sourcePath = "sources/{$p['slug']}-v{$p['version']}.zip";
            $product->files()->create([
                'version' => $p['version'],
                'file_path' => $sourcePath,
                'size' => '184 MB',
                'changelog' => 'Initial seeded release.',
                'is_latest' => true,
            ]);

            // Write a placeholder source zip so gated downloads stream (replace via admin → Files).
            $abs = storage_path('app/private/'.$sourcePath);
            @mkdir(dirname($abs), 0775, true);
            $zip = new \ZipArchive();
            if ($zip->open($abs, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) === true) {
                $zip->addFromString('README.txt', "RazinSoft source package placeholder for {$p['name']} v{$p['version']}.\nReplace this with the real build via the Filament admin (Product → Files).");
                $zip->close();
            }

            // SEO with sensible defaults
            $product->seo()->updateOrCreate([], [
                'seo_title' => "{$p['name']} — {$p['tagline']}",
                'meta_description' => Str::limit(strip_tags($p['overview']), 155),
                'focus_keyword' => Str::lower($p['name']),
                'canonical_url' => "/products/{$p['slug']}",
                'robots' => 'index,follow',
                'og_title' => $p['name'],
                'og_description' => $p['tagline'],
                'og_image' => $p['hero_image'],
                'twitter_title' => $p['name'],
                'twitter_description' => $p['tagline'],
                'twitter_image' => $p['hero_image'],
                'twitter_card' => 'summary_large_image',
                'brand' => 'RazinSoft',
                'sku' => Str::upper(str_replace('-', '', $p['slug'])),
                'operating_system' => 'Web, Android, iOS',
                'application_category' => 'BusinessApplication',
                'software_version' => $p['version'],
                'price_valid_until' => now()->addYear()->toDateString(),
            ]);
        }
    }

    private function plans(): array
    {
        return [
            ['name' => 'Basic', 'blurb' => 'Perfect for small businesses', 'price' => 299, 'is_popular' => false,
                'perks' => ['Single Domain License', 'Basic Features', 'Email Support', '6 Months Updates', 'Source Code Included']],
            ['name' => 'Standard', 'blurb' => 'Most popular choice', 'price' => 599, 'is_popular' => true,
                'perks' => ['5 Domain License', 'All Basic Features', 'Priority Support', '1 Year Updates', 'Free Installation', 'Mobile App Included']],
            ['name' => 'Premium', 'blurb' => 'For large enterprises', 'price' => 1199, 'is_popular' => false,
                'perks' => ['Unlimited Domains', 'All Features Included', '24/7 Priority Support', 'Lifetime Updates', 'Free Customization', 'White Label Rights', 'API Access']],
        ];
    }

    private function features(): array
    {
        return [
            ['title' => 'Multi Vendor Support', 'subtitle' => 'Advanced Commission System', 'color' => 'rose', 'description' => 'Manage unlimited vendors with advanced commission systems, vendor dashboards, and automated payouts.'],
            ['title' => 'Payment Gateway Integration', 'subtitle' => '20+ Payment Methods', 'color' => 'blue', 'description' => 'Support for 20+ payment gateways including PayPal, Stripe, Razorpay, and local payment methods.'],
            ['title' => 'Mobile Apps (iOS & Android)', 'subtitle' => 'Native Apps Included', 'color' => 'purple', 'description' => 'Native mobile applications for iOS and Android with push notifications and offline support.'],
            ['title' => 'Advanced Analytics', 'subtitle' => 'Real-time Reporting', 'color' => 'emerald', 'description' => 'Real-time sales analytics, inventory reports, profit/loss statements, and business intelligence.'],
            ['title' => 'Enterprise Security', 'subtitle' => 'GDPR Compliant', 'color' => 'orange', 'description' => 'Bank-level SSL encryption, role-based access control, audit logs, and GDPR compliance.'],
            ['title' => 'Inventory Management', 'subtitle' => 'Multi-warehouse Support', 'color' => 'pink', 'description' => 'Stock tracking, low stock alerts, barcode scanning, batch management, and warehouse support.'],
            ['title' => 'User Role Management', 'subtitle' => 'Custom Permissions', 'color' => 'red', 'description' => 'Create custom roles with granular permissions, staff management, and activity tracking.'],
            ['title' => 'Multi-Language & Currency', 'subtitle' => '50+ Languages', 'color' => 'sky', 'description' => 'Support for 50+ languages and 100+ currencies with automatic conversion and localization.'],
            ['title' => 'API & Integrations', 'subtitle' => 'REST API Included', 'color' => 'violet', 'description' => 'RESTful API, webhook support, and integrations with popular third-party services.'],
        ];
    }

    private function tech(): array
    {
        return [
            ['name' => 'Laravel', 'color' => 'red'], ['name' => 'PHP', 'color' => 'purple'], ['name' => 'Flutter', 'color' => 'sky'],
            ['name' => 'MySQL', 'color' => 'orange'], ['name' => 'REST API', 'color' => 'emerald'], ['name' => 'Bootstrap', 'color' => 'violet'],
            ['name' => 'Vue.js', 'color' => 'teal'], ['name' => 'Redis', 'color' => 'red'],
        ];
    }

    private function suitable(): array
    {
        return ['Beauty & Health Shop', 'Toys & Kids Shop', 'Home & Furniture Shop', "Men's & Women's Fashion Shop",
            'Watch & Jewelry Shop', 'Electronics & Computers Shop', 'Sports & Outdoors Shop', 'Furniture Shop', 'Clothing Store'];
    }

    private function docs(): array
    {
        return [
            ['title' => 'Installation Guide', 'type' => 'guide', 'url' => '#'],
            ['title' => 'User Manual', 'type' => 'manual', 'url' => '#'],
            ['title' => 'Video Tutorials', 'type' => 'video', 'url' => '#'],
            ['title' => 'API Documentation', 'type' => 'api', 'url' => '#'],
            ['title' => 'FAQ', 'type' => 'faq', 'url' => '#'],
        ];
    }

    private function faqs(): array
    {
        return [
            ['question' => 'Does this support multiple languages?', 'answer' => 'Yes — 50+ languages and full RTL support are included out of the box, with an admin panel to manage translations.'],
            ['question' => 'Is the source code included?', 'answer' => 'Yes. Every paid plan includes the full, un-encrypted source code download with lifetime access.'],
            ['question' => 'How do updates work?', 'answer' => 'Updates are delivered through your account dashboard; the duration depends on your plan (6 months to lifetime).'],
        ];
    }

    private function gallery(string $slug): array
    {
        $img = "products/{$slug}.jpg";
        return [
            ['name' => 'Website', 'images' => [['image' => 'blog/blog-1.jpg', 'caption' => 'Homepage']]],
            ['name' => 'Admin', 'images' => [['image' => 'blog/blog-3.jpg', 'caption' => 'Admin Dashboard']]],
            ['name' => 'Mobile App', 'images' => [['image' => 'hero-1.jpg', 'caption' => 'Mobile App']]],
            ['name' => 'Customer App', 'images' => [['image' => 'hero-2.jpg', 'caption' => 'Customer App']]],
        ];
    }

    private function products(): array
    {
        $base = [
            ['slug' => 'ready-ecommerce', 'name' => 'Ready eCommerce', 'tagline' => 'Complete Multi-Vendor eCommerce CMS', 'category' => 'eCommerce', 'badge' => 'best_seller', 'version' => '4.3.2', 'price' => 49, 'ext_price' => 249, 'rating' => 4.9, 'reviews_count' => 284, 'sales_count' => 750, 'hero_image' => 'products/ready-ecommerce.jpg', 'thumbnail' => 'products/ready-ecommerce.jpg'],
            ['slug' => 'ready-lms', 'name' => 'Ready LMS', 'tagline' => 'Complete eLearning Management System', 'category' => 'Education', 'badge' => 'new', 'version' => '3.1.0', 'price' => 39, 'ext_price' => 199, 'rating' => 4.8, 'reviews_count' => 196, 'sales_count' => 430, 'hero_image' => 'products/ready-lms.jpg', 'thumbnail' => 'products/ready-lms.jpg'],
            ['slug' => 'ready-ride', 'name' => 'Ready Ride', 'tagline' => 'Ride Sharing & Taxi Booking Platform', 'category' => 'Booking', 'badge' => null, 'version' => '2.5.1', 'price' => 59, 'ext_price' => 299, 'rating' => 4.7, 'reviews_count' => 143, 'sales_count' => 310, 'hero_image' => 'products/ready-ride.jpg', 'thumbnail' => 'products/ready-ride.jpg'],
            ['slug' => 'ready-pos', 'name' => 'Ready POS', 'tagline' => 'POS with Inventory Management System', 'category' => 'Retail', 'badge' => null, 'version' => '3.0.0', 'price' => 45, 'ext_price' => 229, 'rating' => 4.8, 'reviews_count' => 167, 'sales_count' => 520, 'hero_image' => 'products/ready-pos.jpg', 'thumbnail' => 'products/ready-pos.jpg'],
            ['slug' => 'ready-grocery', 'name' => 'Ready Grocery', 'tagline' => 'Multipurpose Grocery & eCommerce Solution', 'category' => 'eCommerce', 'badge' => null, 'version' => '2.8.0', 'price' => 49, 'ext_price' => 249, 'rating' => 4.7, 'reviews_count' => 112, 'sales_count' => 280, 'hero_image' => 'products/ready-grocery.jpg', 'thumbnail' => 'products/ready-grocery.jpg'],
            ['slug' => 'ready-laundry', 'name' => 'Ready Laundry', 'tagline' => 'Multi-Store Laundry Booking System', 'category' => 'Booking', 'badge' => null, 'version' => '1.9.0', 'price' => 29, 'ext_price' => 149, 'rating' => 4.6, 'reviews_count' => 87, 'sales_count' => 190, 'hero_image' => 'products/ready-laundry.jpg', 'thumbnail' => 'products/ready-laundry.jpg'],
        ];

        return array_map(function ($p) {
            $p['status'] = 'published';
            $p['currency'] = 'USD';
            $p['is_featured'] = true;
            $p['overview'] = "{$p['name']} is an enterprise-ready, fully-featured {$p['tagline']} built with Laravel and Flutter. Pay once and own it forever — the package ships with complete source code, native mobile apps, a powerful admin panel, and lifetime updates. Designed for startups and growing businesses that need to launch fast without compromising on scalability, security, or performance.";
            $p['plans'] = $this->plans();
            $p['gallery'] = $this->gallery($p['slug']);
            return $p;
        }, $base);
    }
}
