<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\SendEmailCampaign;
use App\Models\EmailCampaign;
use App\Models\EmailSuppression;
use App\Models\EmailTemplate;
use App\Models\MapsLead;
use App\Models\MapsOutreachSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * One-click outreach to collected Maps leads, grouped by product.
 *
 * The generic campaign screen sends one template to one audience, so covering
 * fifteen product lines meant building fifteen campaigns by hand and picking the
 * right letter for each. This does that in one pass: tick the products worth
 * mailing, press send, and each gets its own campaign with its own template.
 *
 * The sending itself is the existing machinery — EmailCampaign and
 * SendEmailCampaign — so batching, suppression, tracking and the per-lead
 * "contacted" flag all behave exactly as they do everywhere else.
 */
class MapsCampaignController extends Controller
{
    public function index(): View
    {
        $settings = MapsOutreachSetting::current();

        return view('admin.email.maps-campaign', [
            'segments' => $this->segments($settings),
            'settings' => $settings,
            'recent' => EmailCampaign::where('name', 'like', 'Maps outreach:%')
                ->latest('id')->limit(8)->get(),
        ]);
    }

    public function send(Request $request)
    {
        $data = $request->validate([
            'products' => ['required', 'array', 'min:1'],
            'products.*' => ['string'],
        ]);

        $settings = MapsOutreachSetting::current();
        $segments = $this->segments($settings)->keyBy('product');

        $made = 0;
        $leads = 0;

        foreach ($data['products'] as $product) {
            $segment = $segments->get($product);

            // Silently skipping an unsendable segment would look like it worked.
            if (! $segment || $segment['ready'] === 0 || ! $segment['template_id']) {
                continue;
            }

            $campaign = EmailCampaign::create([
                'name' => "Maps outreach: {$product} - ".now()->format('d M Y, H:i'),
                'subject' => $segment['subject'],
                'email_template_id' => $segment['template_id'],
                'email_config_id' => $settings->email_config_id,
                /*
                 * Stored as the filter, not a frozen list: the audience is
                 * resolved again when the job runs, so a lead that becomes
                 * suppressed in between is still dropped.
                 */
                'audience' => ['type' => 'maps_category', 'values' => $segment['categories']],
                'status' => 'scheduled',
                'created_by' => $request->user()->id,
            ]);

            SendEmailCampaign::dispatch($campaign->id);

            $made++;
            $leads += $segment['ready'];
        }

        if ($made === 0) {
            return back()->with('error', 'Nothing was sent — those segments have no reachable leads, or no active template.');
        }

        return back()->with(
            'status',
            "Queued {$made} campaign(s) to about {$leads} lead(s). Watch Email Manager > Sent Log for delivery.",
        );
    }

    /**
     * Leads ready to be mailed, one row per product line.
     *
     * "Ready" is deliberately strict: a shared-inbox address was found, nothing
     * has been sent to that lead yet, and the address is not suppressed. The
     * count on screen is the count that will actually be mailed.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function segments(MapsOutreachSetting $settings): Collection
    {
        // Categories that actually have reachable leads, with their counts.
        $counts = MapsLead::query()
            ->whereNotNull('email')->where('email', '!=', '')
            ->whereNull('outreach_sent_at')
            ->whereNotNull('category')->where('category', '!=', '')
            ->selectRaw('category, COUNT(*) as total')
            ->groupBy('category')
            ->pluck('total', 'category');

        if ($counts->isEmpty()) {
            return collect();
        }

        // Suppressed addresses must not be counted as reachable.
        $suppressed = $this->suppressedByCategory($counts->keys()->all());

        $rows = collect();

        foreach (array_keys(config('maps-products', [])) as $product) {
            $categories = [];
            $ready = 0;

            foreach ($counts as $category => $total) {
                $lead = new MapsLead(['category' => $category]);
                if ($lead->product() !== $product) {
                    continue;
                }
                $categories[] = $category;
                $ready += max(0, $total - ($suppressed[$category] ?? 0));
            }

            if (! $categories) {
                continue;
            }

            $key = $settings->templateFor($product);
            $template = EmailTemplate::where('key', $key)->where('is_active', true)->first();

            $rows->push([
                'product' => $product,
                'categories' => $categories,
                'ready' => $ready,
                'template_key' => $key,
                'template_id' => $template?->id,
                'template_name' => $template?->name ?? "missing or inactive: {$key}",
                'subject' => $template?->subject ?? '',
            ]);
        }

        return $rows->sortByDesc('ready')->values();
    }

    /**
     * How many reachable leads per category are on the suppression list, so the
     * "ready" figure is not inflated by people who have opted out.
     *
     * @param  string[]  $categories
     * @return array<string, int>
     */
    private function suppressedByCategory(array $categories): array
    {
        $leads = MapsLead::query()
            ->whereIn('category', $categories)
            ->whereNotNull('email')->where('email', '!=', '')
            ->whereNull('outreach_sent_at')
            ->get(['category', 'email']);

        $blocked = EmailSuppression::filter($leads->pluck('email')->all());

        if (! $blocked) {
            return [];
        }

        return $leads
            ->filter(fn ($l) => in_array(mb_strtolower($l->email), $blocked, true))
            ->groupBy('category')
            ->map->count()
            ->all();
    }
}
