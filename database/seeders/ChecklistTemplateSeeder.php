<?php

namespace Database\Seeders;

use App\Models\ChecklistTemplate;
use Illuminate\Database\Seeder;

class ChecklistTemplateSeeder extends Seeder
{
    /** Predefined client-requirement checklists per project type (idempotent). */
    public function run(): void
    {
        $sets = [
            'Installation Service' => [
                null => ['Domain', 'Hosting', 'cPanel', 'SSH', 'Database', 'SSL', 'SMTP', 'Purchase Code'],
            ],
            'App Publish Service' => [
                'Google Play' => ['Google Play Console Access', 'App Name', 'Logo', 'App Icon', 'Screenshots', 'Privacy Policy', 'Support Email', 'Feature Graphic', 'Short Description', 'Full Description'],
                'Apple Store' => ['Apple Developer Account', 'App Store Connect', 'Bundle ID', 'Certificates', 'App Icon', 'Screenshots', 'Privacy Policy', 'Keywords', 'Description', 'Support URL'],
            ],
            'New Software Development' => [
                'New Development' => ['Business Requirements', 'Logo', 'Brand Color', 'Fonts', 'Domain', 'Hosting', 'Reference Apps', 'Wireframe', 'API Documentation'],
                'Payment Gateway' => ['Stripe Keys', 'PayPal', 'SSLCommerz', 'Razorpay', 'Webhook URL'],
            ],
            'Website Development' => [
                null => ['Business Requirements', 'Logo', 'Brand Color', 'Fonts', 'Domain', 'Hosting', 'Reference Sites', 'Content', 'Wireframe'],
            ],
            'Mobile App Development' => [
                'App' => ['Business Requirements', 'Logo', 'App Icon', 'Brand Color', 'Reference Apps', 'Wireframe', 'API Documentation'],
                'Payment Gateway' => ['Stripe Keys', 'PayPal', 'SSLCommerz', 'Razorpay', 'Webhook URL'],
            ],
        ];

        foreach ($sets as $type => $categories) {
            foreach ($categories as $category => $items) {
                foreach ($items as $i => $title) {
                    ChecklistTemplate::updateOrCreate(
                        ['project_type' => $type, 'category' => $category, 'title' => $title],
                        ['required' => true, 'sort_order' => $i],
                    );
                }
            }
        }
    }
}
