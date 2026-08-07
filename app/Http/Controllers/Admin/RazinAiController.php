<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AiFaq;
use App\Models\WhatsappAccount;
use App\Models\WhatsappMessage;
use App\Models\WhatsappSetting;
use App\Services\AiReplyService;
use Illuminate\Http\Request;

/**
 * Razin AI — the auto-reply's control room.
 *
 * One page holds the whole pipeline the way it runs: which numbers answer, when the assistant
 * may speak, the FAQ shelf that answers before OpenAI is ever called, and the prompt and model
 * behind the calls that remain.
 */
class RazinAiController extends Controller
{
    public function index()
    {
        return view('admin.razin-ai', [
            'settings' => AiReplyService::settings(),
            'accounts' => WhatsappAccount::orderBy('position')->orderBy('id')->get(),
            'faqs' => AiFaq::orderBy('position')->orderBy('id')->get(),
            'keyConfigured' => app(AiReplyService::class)->configured(),
            'repliesToday' => WhatsappMessage::where('ai_generated', true)
                ->where('created_at', '>=', now()->startOfDay())->count(),
        ]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'mode' => ['required', 'in:off,always,outside_hours'],
            'office_days' => ['array'],
            'office_days.*' => ['integer', 'between:1,7'],
            'office_start' => ['required', 'date_format:H:i'],
            'office_end' => ['required', 'date_format:H:i'],
            'timezone' => ['required', 'timezone'],
            'model' => ['required', 'string', 'max:100'],
            'max_replies_per_chat_per_day' => ['required', 'integer', 'between:1,200'],
            'system_prompt' => ['required', 'string', 'max:4000'],
            'handover_message' => ['required', 'string', 'max:1000'],
            'account_ids' => ['array'],
            'account_ids.*' => ['integer'],
        ]);

        $settings = WhatsappSetting::current();
        $settings->ai_settings = [
            'mode' => $data['mode'],
            'office_days' => array_map('intval', $data['office_days'] ?? []),
            'office_start' => $data['office_start'],
            'office_end' => $data['office_end'],
            'timezone' => $data['timezone'],
            'model' => $data['model'],
            'max_replies_per_chat_per_day' => (int) $data['max_replies_per_chat_per_day'],
            'system_prompt' => $data['system_prompt'],
            'handover_message' => $data['handover_message'],
        ];
        $settings->save();

        // The per-number switches: exactly the ticked ones answer, everything else stays quiet.
        $enabled = array_map('intval', $data['account_ids'] ?? []);
        WhatsappAccount::query()->update(['ai_reply_enabled' => false]);
        if ($enabled) {
            WhatsappAccount::whereIn('id', $enabled)->update(['ai_reply_enabled' => true]);
        }

        return back()->with('status', 'Razin AI settings saved.');
    }

    public function storeFaq(Request $request)
    {
        $data = $request->validate([
            'keywords' => ['required', 'string', 'max:500'],
            'reply' => ['required', 'string', 'max:2000'],
        ]);

        AiFaq::create($data + ['position' => (int) AiFaq::max('position') + 1]);

        return back()->with('status', 'FAQ added — it now answers before OpenAI is asked.');
    }

    public function toggleFaq(AiFaq $faq)
    {
        $faq->update(['is_active' => ! $faq->is_active]);

        return back();
    }

    public function destroyFaq(AiFaq $faq)
    {
        $faq->delete();

        return back()->with('status', 'FAQ removed.');
    }
}
