<?php

namespace App\Support;

use App\Models\ContactNumber;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Works out which leads are already clients. A lead and a client are the same person when
 * they share a normalized phone number (any of them — people give several) or an email.
 * Only clients the actor may open are returned, so a link never lands on a 403.
 */
class ContactMatcher
{
    /**
     * @param  Collection<int, Lead>  $leads
     * @return array<int, Collection<int, User>>  lead id => matching clients
     */
    public static function clientsForLeads(User $actor, Collection $leads): array
    {
        $leadIds = $leads->pluck('id')->all();
        if (! $leadIds) {
            return [];
        }

        // lead id => [e164, …] for every number those leads own.
        $leadNumbers = ContactNumber::where('contactable_type', Lead::class)
            ->whereIn('contactable_id', $leadIds)->whereNotNull('e164')
            ->get(['contactable_id', 'e164']);

        // e164 => [client id, …]
        $clientNumbers = $leadNumbers->isEmpty()
            ? collect()
            : ContactNumber::where('contactable_type', User::class)
                ->whereIn('e164', $leadNumbers->pluck('e164')->unique()->all())
                ->get(['contactable_id', 'e164']);

        // lead id => email, plus the reverse lookup for clients.
        $emails = $leads->pluck('email', 'id')->filter()->map(fn ($e) => mb_strtolower(trim($e)));

        $byEmail = collect();
        if ($emails->isNotEmpty()) {
            $byEmail = User::clients()->clientVisibleTo($actor)
                ->whereIn(\Illuminate\Support\Facades\DB::raw('lower(email)'), $emails->unique()->values()->all())
                ->pluck('id', \Illuminate\Support\Facades\DB::raw('lower(email)'));
        }

        // One fetch for every client we might link to (respecting the actor's scope).
        $ids = $clientNumbers->pluck('contactable_id')->merge($byEmail->values())->unique()->all();
        $clients = $ids
            ? User::clients()->clientVisibleTo($actor)->whereIn('id', $ids)->get(['id', 'name', 'email', 'company'])->keyBy('id')
            : collect();

        $clientIdsByE164 = $clientNumbers->groupBy('e164')->map(fn ($rows) => $rows->pluck('contactable_id')->unique());
        $e164sByLead = $leadNumbers->groupBy('contactable_id')->map(fn ($rows) => $rows->pluck('e164')->unique());

        $out = [];
        foreach ($leads as $lead) {
            $matched = collect();

            foreach ($e164sByLead[$lead->id] ?? [] as $e164) {
                foreach ($clientIdsByE164[$e164] ?? [] as $cid) {
                    if ($client = $clients[$cid] ?? null) {
                        $matched[$client->id] = $client;
                    }
                }
            }

            $email = mb_strtolower(trim((string) $lead->email));
            if ($email !== '' && ($cid = $byEmail[$email] ?? null) && ($client = $clients[$cid] ?? null)) {
                $matched[$client->id] = $client;
            }

            if ($matched->isNotEmpty()) {
                $out[$lead->id] = $matched->values();
            }
        }

        return $out;
    }

    /** @return Collection<int, User> the clients matching one lead */
    public static function clientsForLead(User $actor, Lead $lead): Collection
    {
        return collect(self::clientsForLeads($actor, collect([$lead]))[$lead->id] ?? []);
    }
}
