{{--
  Outreach automation.

  Two halves: what the pipeline is currently doing (funnel + readiness), and the
  switches that control it. The state comes first on purpose — the usual question
  here is "why is nothing sending", and the answer is almost always one of the
  readiness checks rather than a setting.
--}}
@extends('admin.layouts.app')
@section('title', 'Outreach Automation')

@push('head')
    <style>
        .oa { --line:#e2e8f0; --muted:#64748b; --green:#16a06b; }
        .oa .grid2 { display:grid; grid-template-columns:repeat(auto-fit,minmax(300px,1fr)); gap:16px; }
        .oa .box { padding:16px 18px; background:#fff; border:1px solid var(--line); border-radius:12px; }
        .oa .box h2 { margin:0 0 12px; font-size:12px; text-transform:uppercase;
                      letter-spacing:.4px; color:var(--muted); }
        .oa .funnel { display:grid; grid-template-columns:repeat(auto-fit,minmax(110px,1fr)); gap:10px; }
        .oa .step { padding:10px 12px; background:#f8fafc; border:1px solid var(--line); border-radius:9px; }
        .oa .step b { display:block; font-size:20px; line-height:1.2; }
        .oa .step span { color:var(--muted); font-size:11px; text-transform:uppercase; letter-spacing:.3px; }
        .oa .check { display:flex; gap:9px; padding:8px 0; border-bottom:1px solid #f1f5f9; font-size:13px; }
        .oa .check:last-child { border-bottom:0; }
        .oa .dot { flex:0 0 auto; width:9px; height:9px; margin-top:5px; border-radius:50%; }
        .oa .dot--ok { background:var(--green); }
        .oa .dot--bad { background:#ef4444; }
        .oa .check b { display:block; font-weight:600; }
        .oa .check span { color:var(--muted); font-size:12px; }
        .oa label.row { display:flex; gap:9px; align-items:flex-start; padding:9px 0; }
        .oa label.row input { margin-top:3px; accent-color:var(--green); }
        .oa label.row b { display:block; }
        .oa label.row span { color:var(--muted); font-size:12px; }
        .oa .field { display:block; margin-bottom:14px; }
        .oa .field > span { display:block; margin-bottom:5px; font-size:12px; font-weight:600; color:var(--muted); }
        .oa select, .oa input[type=number] { width:100%; height:40px; padding:0 10px;
                                             border:1px solid var(--line); border-radius:8px; background:#fff; }
        .oa .countries { max-height:150px; overflow-y:auto; border:1px solid var(--line); border-radius:8px; padding:6px 9px; }
        .oa .warn { margin-bottom:16px; padding:10px 13px; background:#fef3c7; border:1px solid #fde68a;
                    border-radius:9px; font-size:13px; }
        .oa .flash { margin-bottom:16px; padding:10px 13px; background:#dcfce7;
                     border-left:3px solid var(--green); border-radius:7px; font-size:13px; }
        .oa .actions { margin-top:6px; }
        .oa .btn { padding:9px 18px; border:0; border-radius:8px; background:var(--green);
                   color:#fff; font:inherit; font-weight:600; cursor:pointer; }
    </style>
@endpush

@section('content')
    <div class="oa">
        @if (session('status'))
            <div class="flash">{{ session('status') }}</div>
        @endif

        @if ($errors->any())
            <div class="warn">{{ $errors->first() }}</div>
        @endif

        <div class="grid2" style="margin-bottom:16px">
            <div class="box">
                <h2>Where the leads are</h2>
                <div class="funnel">
                    <div class="step"><b>{{ number_format($funnel['collected']) }}</b><span>Collected</span></div>
                    <div class="step"><b>{{ number_format($funnel['with_website']) }}</b><span>Have a site</span></div>
                    <div class="step"><b>{{ number_format($funnel['with_email']) }}</b><span>Have an email</span></div>
                    <div class="step"><b>{{ number_format($funnel['contacted']) }}</b><span>Contacted</span></div>
                    <div class="step"><b>{{ number_format($funnel['opened']) }}</b><span>Opened</span></div>
                    <div class="step"><b>{{ number_format($funnel['clicked']) }}</b><span>Clicked</span></div>
                </div>
                <p style="margin:12px 0 0;font-size:12px;color:var(--muted)">
                    Each column can only be as large as the one before it. Where the
                    numbers fall away is where the pipeline is losing leads.
                </p>
            </div>

            <div class="box">
                <h2>Ready to send?</h2>
                @foreach ($checks as $check)
                    <div class="check">
                        <span class="dot dot--{{ $check['ok'] ? 'ok' : 'bad' }}"></span>
                        <div>
                            <b>{{ $check['label'] }}</b>
                            <span>{{ $check['detail'] }}</span>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        <form method="POST" action="{{ route('admin.email.automation.update') }}">
            @csrf
            @method('PUT')

            <div class="grid2">
                <div class="box">
                    <h2>What runs automatically</h2>

                    <label class="row">
                        <input type="checkbox" name="is_enabled" value="1" @checked($settings->is_enabled)>
                        <div>
                            <b>Outreach enabled</b>
                            <span>Master switch. With this off nothing below happens, whatever it says.</span>
                        </div>
                    </label>

                    <label class="row">
                        <input type="checkbox" name="discover_emails" value="1" @checked($settings->discover_emails)>
                        <div>
                            <b>Look up email addresses</b>
                            <span>Google Maps never shows an email, so each lead's own website is
                                  read for one — the homepage and the usual contact pages.</span>
                        </div>
                    </label>

                    <label class="row">
                        <input type="checkbox" name="auto_send" value="1" @checked($settings->auto_send)>
                        <div>
                            <b>Send without review</b>
                            <span>Mails a lead as soon as an address is found. Leave this off to
                                  collect addresses first and send campaigns by hand.</span>
                        </div>
                    </label>
                </div>

                <div class="box">
                    <h2>How much, how fast</h2>

                    <label class="field">
                        <span>Messages per day</span>
                        <input type="number" name="daily_limit" min="1" max="2000" value="{{ $settings->daily_limit }}">
                    </label>

                    <label class="field">
                        <span>Seconds between messages</span>
                        <input type="number" name="min_gap_seconds" min="10" max="3600" value="{{ $settings->min_gap_seconds }}">
                    </label>

                    <div class="warn" style="margin:0">
                        These two are what protect the domain. A burst of cold mail is the
                        fastest way to get flagged — and once that happens the invoices and
                        password resets stop arriving too. Keep the daily figure low until
                        the bounce rate in Logs looks healthy.
                    </div>
                </div>

                <div class="box">
                    <h2>What gets sent</h2>

                    <label class="field">
                        <span>Template</span>
                        <select name="template_key">
                            @foreach ($templates as $t)
                                <option value="{{ $t->key }}" @selected($settings->template_key === $t->key)>{{ $t->name }}</option>
                            @endforeach
                        </select>
                    </label>

                    <label class="field">
                        <span>Send from</span>
                        <select name="email_config_id">
                            <option value="">Default account</option>
                            @foreach ($configs as $c)
                                <option value="{{ $c->id }}" @selected($settings->email_config_id === $c->id)>
                                    {{ $c->name }} ({{ $c->from_email }}){{ $c->is_active ? '' : ' - inactive' }}
                                </option>
                            @endforeach
                        </select>
                    </label>
                </div>

                <div class="box">
                    <h2>Which countries</h2>
                    @if ($countries->isEmpty())
                        <p style="margin:0;font-size:13px;color:var(--muted)">
                            No leads collected yet, so there is nothing to limit.
                        </p>
                    @else
                        <div class="countries">
                            @foreach ($countries as $country)
                                <label class="row" style="padding:4px 0">
                                    <input type="checkbox" name="allowed_countries[]" value="{{ $country }}"
                                           @checked(in_array($country, $settings->allowed_countries ?? [], true))>
                                    <div><b style="font-weight:500">{{ $country }}</b></div>
                                </label>
                            @endforeach
                        </div>
                        <p style="margin:9px 0 0;font-size:12px;color:var(--muted)">
                            Tick none to allow every country. Worth using where the rules are
                            strict about unsolicited mail.
                        </p>
                    @endif
                </div>
            </div>

            <div class="actions">
                <button class="btn" type="submit">Save settings</button>
            </div>
        </form>
    </div>
@endsection
