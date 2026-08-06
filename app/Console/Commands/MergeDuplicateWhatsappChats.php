<?php

namespace App\Console\Commands;

use App\Models\WhatsappChat;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Joins conversations that were split across two addresses for the same person.
 *
 * WhatsApp hands us the same contact under their phone number and under a per-account privacy id
 * ending @lid. Until the chat lookup was taught to resolve by number, starting a chat by hand and
 * then receiving their reply produced two rows — same number, two halves of one conversation.
 *
 * Only exact number matches are joined. Two countries share digit patterns often enough that a
 * partial match would merge strangers, which is far worse than the split it repairs.
 */
class MergeDuplicateWhatsappChats extends Command
{
    protected $signature = 'whatsapp:merge-duplicate-chats {--apply : Perform the merge (otherwise only reports it)}';

    protected $description = 'Join WhatsApp chats that are the same number split across two addresses';

    public function handle(): int
    {
        $groups = WhatsappChat::where('chat_type', '!=', 'group')
            ->whereNotNull('phone')->where('phone', '!=', '')
            ->orderBy('id')
            ->get(['id', 'account_id', 'wa_id', 'phone', 'name', 'lead_id', 'client_id', 'unread_count'])
            ->groupBy(fn ($c) => $c->account_id.':'.preg_replace('/\D/', '', $c->phone))
            ->filter(fn ($g) => $g->count() > 1);

        if ($groups->isEmpty()) {
            $this->info('  Nothing split — every number has one chat.');

            return self::SUCCESS;
        }

        $rows = [];
        foreach ($groups as $key => $group) {
            $keep = $group->first();                 // the oldest row keeps the history
            $drop = $group->slice(1);
            $rows[] = [
                explode(':', $key)[1],
                '#'.$keep->id.'  '.$keep->wa_id,
                $drop->map(fn ($c) => '#'.$c->id.'  '.$c->wa_id)->join(', '),
                $drop->sum(fn ($c) => $c->messages()->count()),
            ];
        }

        $this->line('');
        $this->info('  numbers split in two: '.$groups->count());
        $this->table(['number', 'keeping', 'merging in', 'messages moved'], $rows);

        if (! $this->option('apply')) {
            $this->line('');
            $this->comment('  Nothing changed. Re-run with --apply to merge.');

            return self::SUCCESS;
        }

        $merged = 0;
        foreach ($groups as $group) {
            DB::transaction(function () use ($group, &$merged) {
                $keep = WhatsappChat::find($group->first()->id);

                foreach ($group->slice(1) as $row) {
                    $chat = WhatsappChat::find($row->id);
                    if (! $chat || ! $keep) {
                        continue;
                    }

                    DB::table('whatsapp_messages')->where('chat_id', $chat->id)->update(['chat_id' => $keep->id]);
                    DB::table('whatsapp_notes')->where('chat_id', $chat->id)->update(['chat_id' => $keep->id]);

                    // Labels are a pivot: move only the ones the surviving chat does not already
                    // carry, or the insert collides with itself.
                    $have = DB::table('whatsapp_chat_label')->where('chat_id', $keep->id)->pluck('label_id')->all();
                    DB::table('whatsapp_chat_label')->where('chat_id', $chat->id)
                        ->whereNotIn('label_id', $have ?: [0])->update(['chat_id' => $keep->id]);
                    DB::table('whatsapp_chat_label')->where('chat_id', $chat->id)->delete();

                    // The duplicate goes first: (account_id, wa_id) is unique, so the survivor
                    // cannot take that address while the row holding it still exists.
                    $chat->delete();

                    // Keep whatever is filled in on either side — a merge should never lose a
                    // judgement or a linked lead that somebody entered.
                    $keep->fill([
                        'wa_id' => str_contains((string) $keep->wa_id, '@') ? $keep->wa_id : $chat->wa_id,
                        'name' => $keep->name ?: $chat->name,
                        'profile_name' => $keep->profile_name ?: $chat->profile_name,
                        'avatar_path' => $keep->avatar_path ?: $chat->avatar_path,
                        'lead_id' => $keep->lead_id ?: $chat->lead_id,
                        'client_id' => $keep->client_id ?: $chat->client_id,
                        'lead_quality' => $keep->lead_quality ?: $chat->lead_quality,
                        'product_category' => $keep->product_category ?: $chat->product_category,
                        'product_sub_category' => $keep->product_sub_category ?: $chat->product_sub_category,
                        'unread_count' => (int) $keep->unread_count + (int) $chat->unread_count,
                        'last_message_at' => max($keep->last_message_at, $chat->last_message_at),
                        'last_message_preview' => $chat->last_message_at > $keep->last_message_at
                            ? $chat->last_message_preview
                            : $keep->last_message_preview,
                    ])->save();

                    $merged++;
                }
            });
        }

        $this->line('');
        $this->info('  chats merged away: '.$merged);

        return self::SUCCESS;
    }
}
