@extends('layouts.app')
@section('title', 'Register Reconciliation')

@section('content')
<style>
	.rr-wrap { font-family: "Inter Tight", system-ui, sans-serif; color: #1F1B16; max-width: 860px; margin: 24px auto; padding: 0 16px; }
	.rr-card { background: #FAF6EE; border: 1px solid #ECE3CF; border-radius: 14px; padding: 22px 24px; margin-bottom: 18px; }
	.rr-card h2 { margin: 0 0 4px; font-size: 20px; font-weight: 800; }
	.rr-sub { font-size: 13px; color: #8E8273; margin-bottom: 14px; }
	.rr-nav { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; margin-bottom: 12px; }
	.rr-nav a, .rr-btn { padding: 8px 14px; border-radius: 9px; background: #fff; border: 1px solid #DFD2B3; color: #1F1B16; font-weight: 700; font-size: 13px; text-decoration: none; cursor: pointer; font-family: inherit; }
	.rr-btn-dark { background: #1F1B16; color: #FAF6EE; border-color: #1F1B16; }
	.rr-store { margin-top: 16px; }
	.rr-store h3 { font-size: 16px; font-weight: 800; margin: 0 0 4px; }
	.rr-totals { font-size: 13px; color: #5A5045; margin-bottom: 8px; }
	.rr-item { background: #fff; border: 1px solid #ECE3CF; border-radius: 10px; padding: 10px 12px; margin-bottom: 6px; font-size: 14px; }
	.rr-ask { font-weight: 700; color: #8A3A2E; }
	.rr-note { font-size: 12px; color: #2F6B3E; margin-top: 4px; }
	.rr-ok { color: #2F6B3E; font-weight: 600; font-size: 14px; }
	.rr-input { width: 100%; padding: 10px 12px; font-family: inherit; font-size: 14px; border: 1px solid #DFD2B3; border-radius: 9px; background: #fff; }
	.rr-msg { padding: 10px 14px; border-radius: 10px; margin-bottom: 14px; font-size: 13px; }
	.rr-flow { display:grid; grid-template-columns:1fr 1fr; gap:10px; }
	.rr-flow div { background:#fff; border:1px solid #ECE3CF; border-radius:10px; padding:12px; }
	.rr-flow b { display:block; font-size:15px; margin-bottom:4px; }
	.rr-g { background:#fff; border:1px solid #ECE3CF; border-radius:10px; padding:10px 12px; margin-bottom:6px; font-size:14px; }
	.rr-g summary { cursor:pointer; }
	.rr-g div { margin-top:6px; }
	@media (max-width: 640px) { .rr-flow { grid-template-columns:1fr; } }
	.rr-pre { white-space: pre-wrap; font-size: 12px; background: #fff; border: 1px solid #ECE3CF; border-radius: 10px; padding: 12px; }
</style>

<div class="rr-wrap">
	@if(session('status'))
		<div class="rr-msg" style="background:{{ session('status')['success'] ? '#E6F4EA' : '#FDE8E8' }}; color:{{ session('status')['success'] ? '#2F6B3E' : '#8A3A2E' }};">
			{{ session('status')['msg'] }}
		</div>
	@endif

	<details class="rr-card" {{ empty($day_note) ? 'open' : '' }}>
		<summary style="cursor:pointer;font-size:18px;font-weight:800;">How register recon works</summary>
		<div style="display:grid;gap:12px;margin-top:12px;font-size:14px;">
			<div>Every sale happens in <strong>two places</strong>: the customer pays on <strong>Clover</strong> (the card machine), and the cashier rings the records in the <strong>ERP</strong> (so our stock goes down). Recon is checking that both sides tell the same story.</div>
			<div class="rr-flow">
				<div><b>Check 1: Sales</b>Every ERP sale should have a Clover payment for the same amount, and every Clover payment should have an ERP sale.</div>
				<div><b>Check 2: Cash drawer</b>At close, the cash counted should equal: starting cash + cash sales &minus; cash paid for buys &minus; safe drops.</div>
			</div>
			<div>When something doesn't match, it shows up below as a flag. Your job is to find out <strong>why</strong>, get it fixed, and write what happened in your own words.</div>
		</div>
	</details>

	<div class="rr-card">
		<h2>Register reconciliation</h2>
		<div class="rr-sub">Everything that needs fixing or a question to a cashier for the day. Posts to Slack every morning at 8am for the day before.</div>
		<div class="rr-nav">
			<a href="/register-recon?date={{ $prev_date }}">&larr; Prev day</a>
			<strong>{{ \Carbon\Carbon::parse($date)->format('l, M j, Y') }}</strong>
			@if($allow_next) <a href="/register-recon?date={{ $next_date }}">Next day &rarr;</a>@endif
			<a href="/pos/recent-feed?date={{ $date }}">Open recent feed</a>
			@if(!empty($posted[$date])) <span style="font-size:12px;color:#8E8273;">Posted to Slack {{ $posted[$date] }}</span>@endif
		</div>

		@if($error)
			<div class="rr-msg" style="background:#FDE8E8;color:#8A3A2E;">Could not build this day: {{ $error }}</div>
		@elseif($report)
			@if($report['issue_count'] === 0)
				<div class="rr-ok">Everything matches. Nothing to fix.</div>
			@endif
			@foreach($report['stores'] as $s)
				<div class="rr-store">
					<h3>{{ $s['name'] }} <a href="{{ $s['url'] }}" style="font-size:12px;font-weight:600;margin-left:6px;">discrepancies</a></h3>
					<div class="rr-totals">
						ERP ${{ number_format($s['erp'], 2) }} ({{ $s['erp_count'] }})
						&middot; Clover ${{ number_format($s['clover'], 2) }} ({{ $s['clover_count'] }})
						&middot; @if(abs($s['diff']) < 1) matches @else {{ $s['diff'] > 0 ? 'Clover' : 'ERP' }} higher by ${{ number_format(abs($s['diff']), 2) }} @endif
					</div>
					@forelse(\App\Utils\RegisterReconUtil::shortRows($s) as $row)
						<div class="rr-item"><strong>{{ $row[0] }}</strong> &nbsp; @if($row[1] !== '') <a href="{{ $row[2] }}">{{ str_replace('*', '', $row[1]) }}</a> @endif @if(!empty($row[3])) {!! ($row[1] !== '' ? '; ' : '') . \App\Utils\RegisterReconUtil::draftBuyText($row[3], function ($name, $url) { return '<a href="' . e($url) . '">' . e($name) . '</a>'; }) !!} @endif</div>
					@empty
						@if(abs($s['diff']) < 5) <div class="rr-ok">All reconciled</div> @else <div class="rr-ok" style="color:#8A3A2E;">Nothing specific flagged, but the totals differ - please review the recent feed.</div> @endif
					@endforelse
					@foreach($s['items'] as $it)
						@if(\App\Utils\RegisterReconUtil::isExplained($it))
							<div class="rr-item" style="opacity:.8;"><strong>{{ $it['ask'] }}</strong> &nbsp; {{ $it['detail'] ?? '' }}<div class="rr-note">Explained: {{ $it['note'] }}</div></div>
						@endif
					@endforeach
				</div>
			@endforeach

			@php
				$guide = [
					'no_clover' => ['ERP sale with no Clover charge', 'The cashier rang the sale in the ERP, but no payment shows on Clover.', 'Usually a cash sale that was not also rung on Clover, or the card was charged on the other store\'s machine.', 'Ask the cashier: did the customer pay? If yes and it was cash, ring it on Clover too. If the sale never happened, void it in the ERP.'],
					'no_erp' => ['Clover charge with no ERP sale', 'Money came in on Clover, but no sale was rung in the ERP.', 'The cashier charged the card but skipped ringing the records, so our stock still shows them on the shelf.', 'Ask the cashier which records they sold, then ring them in the ERP so the stock is right.'],
					'mismatch' => ['Different amount', 'The ERP sale and the Clover payment are both there, but the amounts are different.', 'A price was changed on one side only, or a discount was given on Clover but not in the ERP (or the other way).', 'Find which amount the customer really paid. Fix the ERP sale to match.'],
					'match' => ['Needs matching', 'An ERP sale and a Clover payment look like the same sale, but the system could not link them by itself.', 'Times or amounts are slightly off.', 'Open the recent feed and match them if they are the same sale.'],
					'drawer' => ['Drawer count is off', 'The cash counted at close is different from what should be in the drawer.', 'A safe drop or cash buy was not logged, change was given wrong, or a cash sale was not rung.', 'First check the safe drops and cash buys for that shift. If they explain it, mark it explained. If not, ask the cashier.'],
					'smallshort' => ['Drawer count is a little off', 'The drawer count is off by a small amount.', 'Usually a counting mistake or change given wrong.', 'Note it. Only ask the cashier if it happens again.'],
					'handover' => ['Handover gap', 'The next shift\'s opening count does not match what the last shift counted at close.', 'Cash moved between shifts (to the safe overnight), or one of the counts was wrong.', 'Check if the cash went to the safe and came back. If yes, mark it explained.'],
					'uncounted' => ['Closed without counting', 'The register was closed without a cash count.', 'The cashier skipped the count or the register closed by itself.', 'Remind the cashier to count the drawer at every close.'],
					'samecount' => ['Same count as opening', 'The close count is exactly the same as the opening count.', 'The cashier probably did not really count.', 'Ask them to count the drawer for real at close.'],
					'changerun' => ['Change run', 'Cash was moved between stores for change.', 'Normal, as long as both stores logged it.', 'Make sure both sides were logged.'],
					'draftbuy' => ['Buy left as a draft', 'A record buy was started but never finished.', 'If we paid cash for it, the drawer looks short because the system does not know cash went out.', 'Ask the cashier if we bought it. Then Accept or Reject the buy.'],
				];
				$kindsToday = [];
				foreach ($report['stores'] as $s) { foreach ($s['items'] as $it) { if (!\App\Utils\RegisterReconUtil::isExplained($it) && isset($guide[$it['kind']])) { $kindsToday[$it['kind']] = ($kindsToday[$it['kind']] ?? 0) + 1; } } }
			@endphp
			@if($kindsToday)
				<div class="rr-guide">
					<h3 style="margin:18px 0 8px;font-size:16px;font-weight:800;">What today's flags mean</h3>
					@foreach($kindsToday as $k => $n)
						@php $g = $guide[$k]; @endphp
						<details class="rr-g">
							<summary><strong>{{ $g[0] }}</strong> <span>({{ $n }})</span></summary>
							<div><b>What it means:</b> {{ $g[1] }}</div>
							<div><b>Why it happens:</b> {{ $g[2] }}</div>
							<div><b>How to fix it:</b> {{ $g[3] }}</div>
						</details>
					@endforeach
				</div>
			@endif

			<div class="rr-summary">
				<h3 style="margin:18px 0 4px;font-size:16px;font-weight:800;">Your summary for this day</h3>
				<div class="rr-sub" style="margin-bottom:8px">In your own words: what was off, why, and what you did about it. Typing only, no pasting.</div>
				@if(!empty($day_note))
					<div class="rr-item" style="background:#E6F4EA"><strong>{{ $day_note['by'] ?? '' }}, {{ $day_note['at'] ?? '' }}:</strong> {{ $day_note['text'] ?? '' }}</div>
				@endif
				<form method="post" action="/register-recon/day-note">
					{!! csrf_field() !!}
					<input type="hidden" name="date" value="{{ $date }}">
					<textarea name="text" class="rr-input rr-nopaste" rows="3" minlength="30" required placeholder="Example: Hollywood was $40 off because Andy paid a seller cash but left the buy as a draft. I asked him, he accepted it, and the drawer now matches.">{{ $day_note['text'] ?? '' }}</textarea>
					<div style="display:flex;gap:10px;align-items:center;margin-top:8px;"><button type="submit" class="rr-btn rr-btn-dark">Save my summary</button><span id="rr-paste-msg" style="font-size:13px;font-weight:700;color:#8A3A2E;"></span></div>
				</form>
			</div>

			@php
				$drawerItems = [];
				foreach ($report['stores'] as $s) {
					foreach ($s['items'] as $it) {
						if (in_array($it['kind'], ['drawer', 'handover', 'smallshort', 'samecount'], true) && !empty($it['note_key'])) $drawerItems[] = [$s['name'], $it];
					}
				}
			@endphp
			@if(!empty($drawerItems))
				<form method="post" action="/register-recon/explain-drawer" style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;margin-top:14px;">
					{!! csrf_field() !!}
					<select name="note_key" class="rr-input" style="max-width:420px;">
						@foreach($drawerItems as $d)
							<option value="{{ $d[1]['note_key'] }}">{{ $d[0] }} - {{ $d[1]['ask'] }}: {{ $d[1]['detail'] ?? '' }}</option>
						@endforeach
					</select>
					<input type="text" name="reason" class="rr-input" placeholder="What happened (blank clears it)" style="max-width:300px;">
					<button type="submit" class="rr-btn">Mark explained</button>
				</form>
			@endif

			<form method="post" action="/register-recon/post" style="margin-top:18px;">
				{!! csrf_field() !!}
				<input type="hidden" name="date" value="{{ $date }}">
				<button type="submit" class="rr-btn rr-btn-dark">Post this day to Slack now</button>
			</form>
		@endif
	</div>

	<div class="rr-card">
		<h2>Log a missed safe drop</h2>
		<div class="rr-sub">A cashier put cash in the safe but entered $0 in the ERP. This records the deposit so their drawer doesn't look short.</div>
		<form method="post" action="/register-recon/missed-drop" style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
			{!! csrf_field() !!}
			<select name="register_id" class="rr-input" style="max-width:320px;">
				@foreach(($recent_registers ?? []) as $rg)
					<option value="{{ $rg->id }}">{{ $rg->first_name }} - {{ $rg->loc }} - opened {{ \Carbon\Carbon::parse($rg->created_at)->format('D g:ia') }} ({{ $rg->status }})</option>
				@endforeach
			</select>
			<select name="phase" class="rr-input" style="max-width:150px;">
				<option value="open">At open</option>
				<option value="close">At close</option>
			</select>
			<input type="text" name="amount" class="rr-input" placeholder="Amount" style="max-width:110px;">
			<input type="text" name="deposit_seq" class="rr-input" placeholder="Deposit #" style="max-width:110px;">
			<button type="submit" class="rr-btn rr-btn-dark">Log drop</button>
		</form>
	</div>

	<div class="rr-card">
		<h2>Slack channel</h2>
		<form method="post" action="/register-recon/settings" style="margin-top:10px;">
			{!! csrf_field() !!}
			<input type="text" name="slack_webhook" class="rr-input" placeholder="https://hooks.slack.com/services/..." autocomplete="off">
			<p style="font-size:13px;margin:8px 0;{{ $masked ? 'color:#2F6B3E' : 'color:#8A3A2E' }};font-weight:600;">
				{{ $masked ? 'Connected - webhook ends in ' . $masked : 'Not connected yet - nothing posts until a webhook is saved.' }}
			</p>
			<div class="rr-sub">In Slack: open #register-reconciliation, Integrations, Add an App, Incoming Webhooks, add to this channel, copy the URL.</div>
			<button type="submit" class="rr-btn rr-btn-dark">Save webhook</button>
		</form>
	</div>

	@if($slack_text)
	<div class="rr-card">
		<h2>Slack message preview</h2>
		<div class="rr-pre">{{ $slack_text }}</div>
	</div>
	@endif
</div>
<script>
document.querySelectorAll('.rr-nopaste').forEach(function (el) {
	el.addEventListener('paste', function (e) {
		e.preventDefault();
		var m = document.getElementById('rr-paste-msg');
		if (m) m.textContent = 'Type it in your own words, no pasting.';
	});
});
</script>
@endsection
