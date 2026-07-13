<?php

namespace Database\Seeders;

use App\Models\Lead;
use App\Models\User;
use Illuminate\Database\Seeder;

/** Demo leads matching the CRM reference design — replace with real data via the admin panel. */
class LeadSeeder extends Seeder
{
    public function run(): void
    {
        // Team members leads get assigned to.
        $team = collect([
            ['name' => 'Arafat Hossain', 'email' => 'arafat@razinsoft.com'],
            ['name' => 'Fahim Rahman', 'email' => 'fahim@razinsoft.com'],
            ['name' => 'Sadia Afrin', 'email' => 'sadia@razinsoft.com'],
        ])->map(fn ($u) => tap(User::firstOrCreate(
            ['email' => $u['email']],
            ['name' => $u['name'], 'password' => 'password', 'role' => User::ROLE_STAFF, 'job_title' => 'Sales Executive'],
        ), fn ($user) => $user->role !== User::ROLE_STAFF ? $user->update(['role' => User::ROLE_STAFF]) : null));

        [$arafat, $fahim, $sadia] = [$team[0]->id, $team[1]->id, $team[2]->id];

        $rows = [
            ['Walton Hi-Tech Industries', 'Hasan Mahmud', 'Manager', 'hasan.mahmud@walton.com', '+880 1712-345678', 'Website', 'new', 'high', $arafat, 'Technology'],
            ['Beximco Pharmaceuticals', 'Nusrat Jahan', 'HR Executive', 'nusrat.jahan@beximco.com', '+880 1812-987654', 'LinkedIn', 'contacted', 'medium', $fahim, 'Healthcare'],
            ['Pran-RFL Group', 'Rashedul Islam', 'IT Manager', 'rashedul.islam@prangroup.com', '+880 1911-223344', 'Facebook', 'qualified', 'high', $sadia, 'Retail'],
            ['Square Toiletries Ltd.', 'Mizanur Rahman', 'Purchase Head', 'mizan.rahman@squaretoiletries.com', '+880 1687-112233', 'Website', 'proposal', 'high', $arafat, 'Retail'],
            ['Akij Group', 'Tanjina Akter', 'Asst. Manager', 'tanjina.akter@akijgroup.com', '+880 1722-334455', 'Facebook', 'negotiation', 'medium', $fahim, 'Logistics'],
            ['Marico Bangladesh', 'Sabbir Hossain', 'Sales Manager', 'sabbir.hossain@marico.com', '+880 1844-556677', 'Other', 'qualified', 'medium', $sadia, 'Retail'],
            ['City Bank PLC', 'Kazi Rafiq', 'Branch Manager', 'kazi.rafiq@citybank.com', '+880 1711-667788', 'Website', 'new', 'low', $arafat, 'Finance'],
            ['Pathao Limited', 'Abdullah Al Mamun', 'Operations Lead', 'mamun@pathao.com', '+880 1966-889900', 'WhatsApp', 'contacted', 'high', $fahim, 'Technology'],
            ['BRAC Bank', 'Farjana Haque', 'Relationship Officer', 'farjana.haque@bracbank.com', '+880 1678-901234', 'LinkedIn', 'lost', 'low', $sadia, 'Finance'],
            ['Energypac Power Ltd.', 'Jahidul Islam', 'Project Manager', 'jahidul.islam@energypac.com', '+880 1933-445566', 'Other', 'new', 'medium', $arafat, 'Technology'],
        ];

        foreach ($rows as $i => [$company, $name, $title, $email, $phone, $source, $status, $priority, $assignee, $industry]) {
            Lead::firstOrCreate(
                ['email' => $email],
                [
                    'full_name' => $name,
                    'phone' => $phone,
                    'company_name' => $company,
                    'job_title' => $title,
                    'lead_source' => $source,
                    'industry' => $industry,
                    'lead_status' => $status,
                    'priority' => $priority,
                    'assigned_to' => $assignee,
                    'team' => 'Sales',
                    'country' => 'Bangladesh',
                    'city' => 'Dhaka',
                    'created_at' => now()->subDays($i)->subHours($i * 2),
                ],
            );
        }
    }
}
