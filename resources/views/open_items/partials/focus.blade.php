@php
    // Famous-musician posts (best performing social). Extend before 12/12/26.
    $socialCal = [
        '2026-11-14' => 'Aaron Copland (born 1900)', '2026-11-15' => 'Petula Clark (born 1932)',
        '2026-11-16' => 'W.C. Handy (born 1873)', '2026-11-17' => 'Gordon Lightfoot (born 1938)',
        '2026-11-20' => 'Duane Allman (born 1946) or Joe Walsh (born 1947)', '2026-11-21' => 'Björk (born 1965)',
        '2026-11-22' => 'Hoagy Carmichael (born 1899)', '2026-11-23' => 'Miley Cyrus (born 1992)',
        '2026-11-24' => 'Freddie Mercury (remembering him, died 1991)', '2026-11-25' => 'Percy Sledge (born 1940)',
        '2026-11-26' => 'Tina Turner (born 1939)', '2026-11-27' => 'Jimi Hendrix (born 1942)',
        '2026-11-28' => 'Randy Newman (born 1943)', '2026-11-29' => 'George Harrison (remembering him, died 2001)',
        '2026-11-30' => 'Billy Idol (born 1955)', '2026-12-01' => 'Bette Midler (born 1945)',
        '2026-12-02' => 'Britney Spears (born 1981)', '2026-12-03' => 'Ozzy Osbourne (born 1948)',
        '2026-12-04' => 'Jay-Z (born 1969)', '2026-12-05' => 'Little Richard (born 1932)',
        '2026-12-06' => 'Dave Brubeck (born 1920)', '2026-12-07' => 'Tom Waits (born 1949)',
        '2026-12-08' => 'John Lennon (remembering him, died 1980) or Jim Morrison (born 1943)',
        '2026-12-12' => 'Frank Sinatra (born 1915)',
    ];
    $tzNow = \Carbon\Carbon::now('America/Los_Angeles')->startOfDay();
    $week = [];
    for ($i = 0; $i < 7; $i++) {
        $d = $tzNow->copy()->addDays($i);
        $week[] = [$d->format('m/d/y'), $i === 0 ? 'Today' : ($i === 1 ? 'Tomorrow' : $d->format('D')), $socialCal[$d->format('Y-m-d')] ?? 'No birthday: post a rare find or new arrival'];
    }
    $jobs = [
        ['Own venue bookings and event partners end to end', ''],
        ['Push preorders before every listening party', ''],
        ['Answer replies to the saved cart email', ''],
        ['Turn record requests into special orders', ''],
        ['Get and answer Google reviews', ''],
    ];
    $jobLinks = [0 => '/website-carts?tab=bookings', 2 => '/website-carts'];
    $prompts = [
        ['Customer replies', 'A customer wrote this: [paste]. Write a short, warm reply from Nivessa Records that answers the question and says exactly what we\'ll do next. Proper grammar, no emojis.'],
        ['Seller leads', 'Someone wants to sell us this collection: [paste]. Write a warm reply asking for photos, a rough count and formats, and offering a time to bring it to our Pico or Hollywood store.'],
        ['Social captions', 'Write a 2-sentence Instagram caption for [Artist]\'s birthday. Mention one well-known album and that we have [album] on vinyl at Nivessa [store]. Add 4 hashtags.'],
        ['Event partners', 'This person wants to do an event at our Hollywood store: [paste]. Write a warm reply with our stage options, ask for their date and expected crowd, and suggest a call this week.'],
        ['Review replies', 'Here is a Google review: [paste]. Write a short, warm reply that thanks them by name. If it is negative, say what we fixed.'],
        ['Need a hand', 'I need to [task]. Here is what I know: [details]. Give me the steps.'],
    ];
@endphp


@php
    $socialDays = [];
    for ($d = 0; $d < 7; $d++) {
        $day = $tzNow->copy()->addDays($d);
        $artist = $socialCal[$day->format('Y-m-d')] ?? null;
        $prompt = $artist
            ? 'Write a 2-sentence Instagram caption for Nivessa Records about ' . $artist . '. If they are alive say happy birthday, if they have passed start with "Remembering". Mention one well-known album and say we have [album] on vinyl at Nivessa [store] right now. Proper grammar, no emojis. Add 4 hashtags.'
            : 'Write a 2-sentence Instagram caption for Nivessa Records about this rare record we just got in: [artist, title, what makes it special]. Say it is at Nivessa [store] right now. Proper grammar, no emojis. Add 4 hashtags.';
        $socialDays[] = [$day->format('D m/d'), $artist ?: 'Rare find or new arrival', $prompt];
    }
@endphp
<div class="oi-card" id="oi-social">
    <div class="oi-head"><h2>Once a week</h2></div>
    <div class="oi-done"><strong>1. Categorize QuickBooks.</strong> Use the bank rules and past categories. <a href="https://qbo.intuit.com" target="_blank" style="color:var(--d-accent-text);font-weight:700">Open</a></div>
    <div class="oi-done"><strong>2. Draft the reorder</strong> for Jon to approve. <a href="{{ url('/reports/reorder') }}" style="color:var(--d-accent-text);font-weight:700">Open</a></div>
    <div class="oi-done"><strong>3. Post a "We buy records" ad</strong> on Craigslist, Nextdoor and Facebook groups.</div>
    <div class="oi-done"><strong>4. Schedule next 7 days of social posts</strong>, one a day at 11am:</div>
    @foreach($socialDays as $sd)
        <div class="oi-done" style="display:flex;justify-content:space-between;gap:10px;align-items:center;flex-wrap:wrap"><span><strong>{{ $sd[0] }}:</strong> {{ $sd[1] }}</span><a class="oi-btn" style="padding:5px 10px;font-size:12px" href="https://claude.ai/new?q={{ rawurlencode($sd[2]) }}" target="_blank">Caption</a></div>
    @endforeach
</div>

<div class="oi-card" id="oi-bigger">
    <div class="oi-head"><h2>If you have time</h2></div>
    @foreach($jobs as $i => $j)
        <div class="oi-done"><strong>{{ $i + 1 }}. {{ $j[0] }}</strong> @if(isset($jobLinks[$i]))<a href="{{ url($jobLinks[$i]) }}" style="color:var(--d-accent-text);font-weight:700">Open</a>@endif</div>
    @endforeach
</div>

<details class="oi-card" id="oi-claude" style="padding:14px 18px">
    <summary style="cursor:pointer;font-weight:800;font-size:16px;color:var(--d-ink)">Claude prompts</summary>
    @foreach($prompts as $p)
        <div class="oi-done"><strong>{{ $p[0] }}:</strong> "{{ $p[1] }}"</div>
    @endforeach
</details>
