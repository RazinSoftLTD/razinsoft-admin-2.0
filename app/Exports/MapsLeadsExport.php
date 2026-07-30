<?php

namespace App\Exports;

use App\Models\MapsLead;
use Illuminate\Database\Eloquent\Builder;

/**
 * Column definition shared by the CSV and Excel exports, so both always emit
 * the same shape.
 *
 * This class intentionally has no dependency on maatwebsite/excel, so the CSV
 * route works on a bare Laravel install. The Excel wrapper that does depend on
 * the package ships as MapsLeadsExcelExport.php.stub - see the README.
 */
class MapsLeadsExport
{
    public function __construct(
        private readonly ?string $search = null,
        private readonly array $filters = [],
    ) {}

    /**
     * @return array<int, string>
     */
    public static function headings(): array
    {
        return [
            'ID', 'Name', 'Category', 'Address', 'Phone', 'Email', 'Website',
            'Rating', 'Reviews', 'Latitude', 'Longitude', 'Opening hours',
            'Business status', 'Plus code', 'Price level',
            'Country', 'City', 'Search category', 'Search query',
            'Google Maps URL', 'Place key', 'Times seen', 'Status',
            'First run', 'Last run', 'Collected at', 'Created at',
            'Product fit', 'Outreach sent at', 'Emails sent', 'Opens', 'Clicks',
            'All emails', 'Shared inboxes',
        ];
    }

    /**
     * @return array<int, mixed>
     */
    public static function row(MapsLead $lead): array
    {
        return [
            $lead->id,
            $lead->name,
            $lead->category,
            $lead->address,
            $lead->phone,
            $lead->email,
            $lead->website,
            $lead->rating,
            $lead->review_count,
            $lead->latitude,
            $lead->longitude,
            self::flattenHours($lead->opening_hours),
            $lead->business_status,
            $lead->plus_code,
            $lead->price_level,
            $lead->search_country,
            $lead->search_city,
            $lead->search_category,
            $lead->search_query,
            $lead->maps_url,
            $lead->place_key,
            $lead->times_seen,
            $lead->status,
            $lead->first_run_id,
            $lead->last_run_id,
            $lead->collected_at?->toDateTimeString(),
            $lead->created_at?->toDateTimeString(),
            // Engagement, so the export can be worked offline as a call list.
            $lead->product(),
            $lead->outreach_sent_at?->toDateTimeString(),
            $lead->engagement()['sent'],
            $lead->engagement()['opens'],
            $lead->engagement()['clicks'],
            // Every address found, and the subset outreach is allowed to use.
            $lead->emails->pluck('email')->implode(', '),
            $lead->emails->where('is_generic', true)->pluck('email')->implode(', '),
        ];
    }

    /** "Mon: 9 AM-6 PM | Tue: Closed" */
    private static function flattenHours(?array $hours): string
    {
        if (! $hours) {
            return '';
        }

        $parts = [];
        foreach ($hours as $day => $value) {
            $parts[] = "{$day}: {$value}";
        }

        return implode(' | ', $parts);
    }

    /**
     * The filtered query both exports iterate over.
     */
    public function query(): Builder
    {
        return MapsLead::query()
            ->search($this->search)
            ->filter($this->filters)
            ->orderBy('id');
    }
}
