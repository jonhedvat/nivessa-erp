@extends('layouts.app')
@section('title', 'Intake Sheet — ' . $offer->buy_record_number)

@section('content')
<section class="content-header no-print">
    <h1>Collection Intake Sheet</h1>
</section>

<section class="content">
    @php
        // Jon 11/23: individually priced items (single records, signed/rare/eBay,
        // $10+ CDs, anything scanned) get listed one by one in Mass Add — they
        // are never "a collection to store in the back". Only bulk lots count
        // toward the 100-item store-it-in-the-back / warehouse call.
        $bfcTypeRules = app(\App\Services\BuyOfferCalculatorService::class)->getRules()['item_types'];
        $bfcBulkUnits = 0;
        $bfcSingleUnits = 0;
        foreach ($offer->lines as $bfcL) {
            $bfcQ = (float) $bfcL->quantity;
            $bfcMode = $bfcTypeRules[$bfcL->item_type]['mode'] ?? 'bulk_fixed';
            if ($bfcMode !== 'bulk_fixed' || !empty($bfcL->upc)) {
                $bfcSingleUnits += $bfcQ;
            } else {
                $bfcBulkUnits += $bfcQ;
            }
        }
        $bfcBulkUnits = (int) round($bfcBulkUnits);
        $bfcSingleUnits = (int) round($bfcSingleUnits);
        $bfcStoreCollection = $bfcBulkUnits >= 100;
        $bfcAllSingles = $bfcBulkUnits === 0 && $bfcSingleUnits > 0;
    @endphp
    <div class="no-print" style="margin-bottom:15px;">
        @if(session('status'))
            <div class="alert alert-{{ session('status.success') ? 'success' : 'danger' }}">
                {{ session('status.msg') }}
            </div>
        @endif
        {{-- Sarah 2026-10-26: say plainly the buy is done and the credit is on the account. --}}
        @if($offer->status === 'accepted')
            @php $bfcCredit = data_get(session('bfc_finished'), 'credit'); @endphp
            <div style="border:3px solid #1e8e3e; background:#e8f5e9; border-radius:6px; padding:14px 18px; margin-bottom:15px; color:#14532d;">
                <div style="font-size:20px; font-weight:800;"><i class="fa fa-check-circle"></i> Buy finished</div>
                @if($bfcCredit)
                    <div style="font-size:17px; margin-top:6px;">
                        <strong>${{ number_format((float) $bfcCredit['amount'], 2) }} store credit is now on {{ $bfcCredit['name'] }}'s account.</strong>
                        New balance: ${{ number_format((float) $bfcCredit['balance'], 2) }}. Do not add it again by hand.
                    </div>
                @elseif($offer->payout_type === 'store_credit' && !$offer->is_donated && (float) $offer->final_offer_credit > 0)
                    <div style="font-size:17px; margin-top:6px;">
                        <strong>${{ number_format((float) $offer->final_offer_credit, 2) }} store credit was added to {{ optional($offer->contact)->name ?: 'the customer' }}'s account</strong> when this buy was finished. Do not add it again by hand.
                    </div>
                @endif
            </div>
            {{-- Sarah 2026-11-09: send the seller a receipt, itemized or total only. --}}
            <div id="bfc_seller_receipt" style="border:1px solid #d0d7de; background:#fff; border-radius:6px; padding:12px 16px; margin-bottom:15px;">
                <div style="font-size:16px; font-weight:700; margin-bottom:8px;"><i class="fa fa-paper-plane"></i> Send receipt to seller</div>
                <div style="margin-bottom:10px;">
                    <label style="font-weight:normal; margin-right:18px; cursor:pointer;">
                        <input type="radio" name="bfc_receipt_mode" value="itemized" checked> Itemized (every item)
                    </label>
                    <label style="font-weight:normal; cursor:pointer;">
                        <input type="radio" name="bfc_receipt_mode" value="total"> Total only
                    </label>
                </div>
                <div class="row">
                    <div class="col-sm-6" style="margin-bottom:8px;">
                        <div class="input-group">
                            <input type="email" id="bfc_receipt_email" class="form-control" placeholder="Seller email" value="{{ $offer->seller_email ?: optional($offer->contact)->email }}">
                            <span class="input-group-btn">
                                <button type="button" class="btn btn-primary bfc-send-receipt" data-channel="email" data-input="#bfc_receipt_email"><i class="fa fa-envelope"></i> Email</button>
                            </span>
                        </div>
                    </div>
                    <div class="col-sm-6" style="margin-bottom:8px;">
                        <div class="input-group">
                            <input type="tel" id="bfc_receipt_phone" class="form-control" placeholder="Seller phone" value="{{ $offer->seller_phone ?: (in_array((string) optional($offer->contact)->mobile, ['', '0'], true) ? '' : optional($offer->contact)->mobile) }}">
                            <span class="input-group-btn">
                                <button type="button" class="btn btn-primary bfc-send-receipt" data-channel="text" data-input="#bfc_receipt_phone"><i class="fa fa-comment"></i> Text</button>
                            </span>
                        </div>
                    </div>
                </div>
                <div id="bfc_receipt_status" style="font-weight:600;"></div>
            </div>
            {{-- Jon 11/23: what to do with the items. Under 100: list them now in Mass Add
                 (it loads this buy and adds its rows). 100+: store the box in the back
                 and list it later. --}}
            @php
                $bfcMassAddUrl = route('product.massCreate', ['buy' => $offer->buy_record_number, 'add' => 1]);
                $bfcCanList = auth()->user()->can('product.create');
                $bfcPlural = function ($n, $w) { return number_format($n) . ' ' . $w . ($n === 1 ? '' : 's'); };
            @endphp
            <div id="bfc_next_step" style="border:2px solid {{ $bfcStoreCollection ? '#8a6d00' : '#3c8dbc' }}; background:{{ $bfcStoreCollection ? '#fff8e1' : '#eef6fc' }}; border-radius:6px; padding:14px 18px; margin-bottom:15px;">
                @if($bfcStoreCollection)
                    <div style="font-size:18px; font-weight:800; margin-bottom:6px;">
                        <i class="fa fa-archive"></i> Next: {{ $bfcPlural($bfcBulkUnits, 'bulk item') }} — store it in the back
                    </div>
                    <div style="font-size:15px; margin-bottom:10px;">This is a big bulk lot (100+ items). Don't list it now: put it in the back, write where below, and it gets listed later.</div>
                    <div class="input-group" style="max-width:460px;">
                        <input type="text" id="bfc_next_storage" class="form-control input-lg" placeholder="Where is it? e.g. Back room, shelf B3" value="{{ $offer->storage_location }}">
                        <span class="input-group-btn">
                            <button type="button" class="btn btn-warning btn-lg" id="bfc_next_storage_save"><i class="fa fa-archive"></i> Stored in the back</button>
                        </span>
                    </div>
                    <div id="bfc_next_storage_status" style="font-weight:600; margin-top:6px;">
                        @if($offer->storage_location)<span style="color:#1e8e3e;"><i class="fa fa-check"></i> Stored at: {{ $offer->storage_location }}</span>@endif
                    </div>
                    @if($bfcCanList && $bfcSingleUnits > 0)
                        <div style="margin-top:12px; padding-top:10px; border-top:1px dashed #d8c27a;">
                            <strong>The {{ $bfcPlural($bfcSingleUnits, 'individually priced item') }}</strong> don't go in the back:
                            <a href="{{ $bfcMassAddUrl }}" class="btn btn-success btn-sm" style="margin-left:6px;"><i class="fa fa-arrow-right"></i> Send to Mass Add</a>
                        </div>
                    @elseif($bfcCanList)
                        <div style="margin-top:8px;"><a href="{{ $bfcMassAddUrl }}"><i class="fa fa-arrow-right"></i> List it in Mass Add now anyway</a></div>
                    @endif
                @else
                    <div style="font-size:18px; font-weight:800; margin-bottom:6px;">
                        <i class="fa fa-list"></i> Next: {{ $bfcPlural($bfcBulkUnits + $bfcSingleUnits, 'item') }} — list {{ ($bfcBulkUnits + $bfcSingleUnits) === 1 ? 'it' : 'them' }} now
                    </div>
                    @if($bfcCanList)
                        <a href="{{ $bfcMassAddUrl }}" class="btn btn-success btn-lg"><i class="fa fa-arrow-right"></i> Send to Mass Add</a>
                        @if(!$bfcAllSingles)
                            <span style="margin-left:10px;">No time now? <a href="#" id="bfc_next_store_instead">Store it in the back instead</a></span>
                        @endif
                    @else
                        <div style="font-size:15px;">Hand {{ ($bfcBulkUnits + $bfcSingleUnits) === 1 ? 'it' : 'them' }} to someone who can list products (Mass Add).</div>
                    @endif
                @endif
            </div>
        @elseif($offer->status === 'draft')
            <div style="border:3px solid #c62828; background:#ffebee; border-radius:6px; padding:14px 18px; margin-bottom:15px; color:#7f1d1d; font-size:17px;">
                <strong>This buy is NOT finished.</strong> Nothing is paid and no store credit is added until you open it and click Accept and finish buy.
            </div>
        @endif
        <button type="button" class="btn btn-primary" onclick="window.print()"><i class="fa fa-print"></i> Print</button>
        <button type="button" class="btn btn-default" id="bfc_copy_discogs_links"><i class="fa fa-copy"></i> Copy Discogs Links</button>
        <a href="{{ route('buy-from-customer.history') }}" class="btn btn-default"><i class="fa fa-arrow-left"></i> Back to History</a>
        <a href="{{ route('buy-from-customer.storage-locations') }}" class="btn btn-default"><i class="fa fa-boxes"></i> Storage Locations</a>
        <span id="bfc_copy_discogs_links_status" class="text-muted"></span>
    </div>

    <div class="box box-solid bfc-intake-sheet">
        <div class="box-body" style="max-width:820px; margin:0 auto; font-size:14px;">
            <div style="text-align:center; margin-bottom:20px;">
                <h2 style="margin-bottom:0;">Collection Intake Sheet</h2>
                <div style="font-size:20px; font-weight:700; letter-spacing:1px;">{{ $offer->buy_record_number }}</div>
                <div class="text-muted">{{ @format_datetime($offer->accepted_at ?? $offer->created_at) }} &middot; {{ optional($offer->location)->name ?? '—' }}</div>
            </div>

            @php $totalItems = $offer->total_item_quantity; @endphp
            {{-- Only a big BULK lot is a warehouse collection; individually priced items are not. --}}
            @if($bfcStoreCollection)
                <div class="bfc-warehouse-label" style="border:4px solid #b0451a; background:#fff3e0; padding:20px; margin-bottom:20px; text-align:center; border-radius:6px;">
                    <div style="font-size:22px; font-weight:800; color:#b0451a; letter-spacing:1px;">&#9888; WAREHOUSE COLLECTION &mdash; DO NOT SHELVE IN STORE &#9888;</div>
                    <div style="font-size:16px; margin-top:8px;">{{ $offer->buy_record_number }} &middot; {{ $bfcBulkUnits }} bulk items</div>
                    <div style="font-size:15px; margin-top:10px; font-weight:600;">&rarr; Take this box to the Warehouse</div>
                    <div style="font-size:12px; color:#7a4a1a; margin-top:8px;">
                        Warehouse is not always staffed. If it's closed, hold this box at {{ optional($offer->location)->name ?: 'the store' }} until it can be dropped off.
                    </div>
                </div>
            @endif

            <table class="table table-bordered" style="margin-bottom:20px;">
                <tr>
                    <th style="width:25%; background:#f9f9f9;">Purchased from</th>
                    <td>
                        @if($offer->seller_first_name || $offer->seller_last_name)
                            {{ trim($offer->seller_first_name . ' ' . $offer->seller_last_name) }}
                        @else
                            {{ $offer->seller_name ?: optional($offer->contact)->name ?: '—' }}
                        @endif
                        @if(!empty($offer->seller_phone)) <br><small>{{ $offer->seller_phone }}</small> @endif
                        @if(!empty($offer->seller_email)) <br><small>{{ $offer->seller_email }}</small> @endif
                    </td>
                </tr>
                <tr>
                    <th style="background:#f9f9f9;">Purchased by</th>
                    <td>{{ optional($offer->createdBy)->user_full_name ?? optional($offer->createdBy)->username ?? '—' }}</td>
                </tr>
                <tr>
                    <th style="background:#f9f9f9;">ID number</th>
                    <td>
                        <strong>{{ $offer->buy_record_number }}</strong>
                        @if(!empty($offer->seller_id_type))
                            <br><small class="text-muted">Seller ID on file: {{ $offer->seller_id_type }} ending {{ $offer->seller_id_last_four ?: '—' }}</small>
                        @endif
                    </td>
                </tr>
                <tr>
                    <th style="background:#f9f9f9;">Value</th>
                    <td>
                        @php
                            $isCredit = $offer->payout_type === 'store_credit';
                            $finalAccepted = $isCredit ? $offer->final_offer_credit : $offer->final_offer_cash;
                            $pmLabel = [
                                'cash_in_store' => 'Cash (in store)',
                                'store_credit' => 'Store credit',
                                'zelle_venmo' => 'Zelle / Venmo',
                            ][$offer->payment_method] ?? ucfirst(str_replace('_', ' ', $offer->payout_type));
                        @endphp
                        @if($offer->is_donated)
                            <strong>Donated</strong>
                            <small class="text-muted">(no payout)</small>
                        @else
                            <strong>@format_currency($finalAccepted)</strong>
                            <small class="text-muted">({{ $pmLabel }})</small>
                        @endif
                    </td>
                </tr>
                @if(!$bfcAllSingles)
                <tr>
                    <th style="background:#f9f9f9;">Storage location</th>
                    <td>
                        <span id="bfc_storage_location_display">
                            {{ $offer->storage_location ?: 'Not yet assigned' }}
                        </span>
                        @if($offer->storage_location_updated_at)
                            <br><small class="text-muted" id="bfc_storage_location_meta">
                                Last set {{ $offer->storage_location_updated_at->format('M j, Y g:ia') }}
                            </small>
                        @else
                            <small class="text-muted" id="bfc_storage_location_meta"></small>
                        @endif
                        <div class="no-print" style="margin-top:8px;">
                            <div class="input-group" style="max-width:400px;">
                                <input type="text" id="bfc_storage_location_input" class="form-control" placeholder="e.g. Back room, shelf B3" value="{{ $offer->storage_location }}">
                                <span class="input-group-btn">
                                    <button type="button" class="btn btn-default" id="bfc_storage_location_save"><i class="fa fa-save"></i> Save</button>
                                </span>
                            </div>
                            <small class="text-muted" id="bfc_storage_location_status"></small>
                        </div>
                    </td>
                </tr>
                @endif
            </table>

            <h4>Contents</h4>
            <table class="table table-condensed table-bordered">
                <thead>
                    <tr>
                        <th>Type</th>
                        <th>Title</th>
                        <th>Discogs Link</th>
                        <th>Grade</th>
                        <th class="text-right">Qty</th>
                        <th>Destination</th>
                    </tr>
                </thead>
                <tbody>
                    @php
                        $dispositionLabels = [
                            'store' => 'Store',
                            'discogs' => 'Discogs',
                            'ebay' => 'eBay',
                            'hollywood' => 'Hollywood',
                            'trash' => 'Trash',
                            'clearance_bin' => 'Clearance Bin',
                        ];
                    @endphp
                    @forelse($offer->lines as $line)
                        <tr>
                            <td>{{ $itemTypes[$line->item_type] ?? $line->item_type }}</td>
                            <td>{{ $line->title ?: '—' }}</td>
                            <td>
                                @if($line->discogs_url)
                                    <a href="{{ $line->discogs_url }}" target="_blank" rel="noopener">{{ $line->discogs_link }}</a>
                                @else
                                    {{ $line->discogs_link ?: '—' }}
                                @endif
                            </td>
                            <td>{{ $line->condition_grade ?: '—' }}</td>
                            <td class="text-right">{{ number_format((float) $line->quantity, 2) }}</td>
                            <td>{{ $dispositionLabels[$line->disposition] ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-muted">No line items.</td></tr>
                    @endforelse
                </tbody>
            </table>

            @if($inputs)
                {{-- Jon 2026-10-26: what the associate typed vs what the system said. Not printed. --}}
                <div class="no-print bfc-inputs">
                    <h4 style="margin-top:25px;">What {{ optional($offer->createdBy)->first_name ?: 'the associate' }} entered vs what the system said</h4>
                    <table class="table table-condensed table-bordered">
                        <thead>
                            <tr>
                                <th>Type</th>
                                <th>Title</th>
                                <th>Grade</th>
                                <th class="text-right">Qty</th>
                                <th class="text-right">Value entered</th>
                                <th>Link entered</th>
                                <th>How the system priced it</th>
                                <th class="text-right">Line value</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($offer->lines as $line)
                                @php
                                    $bfcD = $inputs['demand'][$line->line_order] ?? null;
                                    $bfcV = (float) $line->discogs_median_price;
                                    $bfcHow = [];
                                    if ($bfcV > 0) {
                                        $bfcHow[] = '$' . number_format($bfcV, 2) . ' × ' . round((float) $line->standard_multiplier * 100, 1) . '%' . ((float) $line->grade_multiplier != 1.0 ? ' × grade ' . (float) $line->grade_multiplier : '');
                                    } elseif ((float) $line->unit_rate > 0) {
                                        $bfcHow[] = number_format((float) $line->quantity, 2) . ' × $' . number_format((float) $line->unit_rate, 2) . ((float) $line->grade_multiplier != 1.0 ? ' × grade ' . (float) $line->grade_multiplier : '');
                                    }
                                    if ($bfcD && !empty($bfcD['reason'])) {
                                        $bfcHow[] = $bfcD['reason'];
                                    }
                                    if ($bfcD && (float) ($bfcD['multiplier'] ?? 1) != 1.0 && ($bfcD['level'] ?? '') !== 'margin') {
                                        $bfcHow[] = 'speed adjustment × ' . round((float) $bfcD['multiplier'], 2);
                                    }
                                @endphp
                                <tr>
                                    <td>{{ $itemTypes[$line->item_type] ?? $line->item_type }}</td>
                                    <td>{{ $line->title ?: '—' }}</td>
                                    <td>{{ $line->condition_grade ?: '—' }}</td>
                                    <td class="text-right">{{ number_format((float) $line->quantity, 2) }}</td>
                                    <td class="text-right">{{ $bfcV > 0 ? '$' . number_format($bfcV, 2) : '—' }}</td>
                                    <td style="max-width:220px; word-break:break-all;">
                                        @if($line->discogs_url)
                                            <a href="{{ $line->discogs_url }}" target="_blank" rel="noopener">{{ \Illuminate\Support\Str::limit($line->discogs_link, 70) }}</a>
                                        @else
                                            {{ $line->discogs_link ?: '—' }}
                                        @endif
                                    </td>
                                    <td><small>{{ implode(' · ', $bfcHow) ?: '—' }}</small></td>
                                    <td class="text-right">${{ number_format((float) ($inputs['isCredit'] ? $line->line_credit_total : $line->line_cash_total), 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                    @php
                        $bfcCur = $inputs['isCredit'] ? 'credit' : 'cash';
                        $bfcDiff = round($inputs['paid'] - $inputs['max'], 2);
                    @endphp
                    <table class="table table-bordered" style="max-width:520px;">
                        <tr><th style="width:55%; background:#f9f9f9;">System total ({{ $bfcCur }})</th><td class="text-right">${{ number_format((float) ($inputs['isCredit'] ? $offer->calculated_credit_total : $offer->calculated_cash_total), 2) }}</td></tr>
                        <tr><th style="background:#f9f9f9;">Starting offer on the form</th><td class="text-right">${{ number_format((float) ($inputs['isCredit'] ? $offer->starting_offer_credit : $offer->starting_offer_cash), 2) }}</td></tr>
                        <tr><th style="background:#f9f9f9;">2nd offer on the form</th><td class="text-right">${{ number_format((float) ($inputs['isCredit'] ? $offer->second_offer_credit : $offer->second_offer_cash), 2) }}</td></tr>
                        <tr><th style="background:#f9f9f9;">System max offer (95%)</th><td class="text-right">${{ number_format($inputs['max'], 2) }}</td></tr>
                        <tr><th style="background:#f9f9f9;">Paid</th><td class="text-right"><strong>${{ number_format($inputs['paid'], 2) }}</strong></td></tr>
                        <tr>
                            <th style="background:#f9f9f9;">Paid vs system max</th>
                            <td class="text-right" style="font-weight:700; color:{{ $bfcDiff > 0.009 ? '#c62828' : ($bfcDiff < -0.009 ? '#1e8e3e' : '#111') }};">
                                @if($offer->is_donated) Donated
                                @elseif($bfcDiff > 0.009) ${{ number_format($bfcDiff, 2) }} more
                                @elseif($bfcDiff < -0.009) ${{ number_format(-$bfcDiff, 2) }} less
                                @else Exactly the system max
                                @endif
                            </td>
                        </tr>
                        <tr><th style="background:#f9f9f9;">Reason given</th><td>{{ $offer->price_override_reason ?: '—' }}</td></tr>
                    </table>
                </div>
            @endif

            @if($bfcAllSingles)
            <p style="margin-top:25px; border-top:1px solid #ddd; padding-top:15px;">
                <strong>Instructions:</strong> These items were priced one by one. List them in Mass Add (Send to Mass Add at the top).
            </p>
            @else
            <p style="margin-top:25px; border-top:1px solid #ddd; padding-top:15px;">
                <strong>Instructions:</strong> Print this sheet (or copy the ID number and storage location onto a label)
                and affix it to the box. Store the box at the location noted above.
            </p>
            @endif
        </div>
    </div>
</section>

<style>
    @media print {
        .no-print, .main-header, .main-sidebar, .content-header, .content-wrapper > .content-header,
        .main-footer, .box-header { display: none !important; }
        .content-wrapper { margin-left: 0 !important; }
        .box { border: none !important; box-shadow: none !important; }
    }
</style>

<script>
(function () {
    function onReady(fn) {
        if (typeof jQuery === 'undefined') { setTimeout(function () { onReady(fn); }, 50); return; }
        jQuery(fn);
    }
    onReady(function ($) {
        $('#bfc_copy_discogs_links').on('click', function () {
            // eBay sold links live in the same column; copy only the Discogs ones.
            var links = @json($offer->lines->pluck('discogs_url')->filter()->values()).filter(function (u) { return /discogs\.com/i.test(u); });
            var $status = $('#bfc_copy_discogs_links_status');
            if (!links.length) {
                $status.text('No Discogs links on this collection.');
                return;
            }
            var text = links.join('\n');
            var done = function () { $status.text(links.length + ' link' + (links.length === 1 ? '' : 's') + ' copied.'); };
            var fail = function () { $status.text('Copy failed — select and copy manually.'); };
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(text).then(done, fail);
            } else {
                var $tmp = $('<textarea>').val(text).appendTo('body').select();
                try { document.execCommand('copy'); done(); } catch (e) { fail(); }
                $tmp.remove();
            }
        });

        $('.bfc-send-receipt').on('click', function () {
            var $btn = $(this);
            var $status = $('#bfc_receipt_status');
            var to = $.trim($($btn.data('input')).val());
            if (!to) {
                $status.css('color', '#c62828').text($btn.data('channel') === 'email' ? 'Type the seller\'s email first.' : 'Type the seller\'s phone first.');
                return;
            }
            $('.bfc-send-receipt').prop('disabled', true);
            $status.css('color', '#555').text('Sending...');
            $.post('{{ route('buy-from-customer.send-receipt', $offer->id) }}', {
                _token: $('meta[name="csrf-token"]').attr('content'),
                channel: $btn.data('channel'),
                mode: $('input[name="bfc_receipt_mode"]:checked').val(),
                to: to,
            })
                .done(function (resp) {
                    $status.css('color', resp.success ? '#1e8e3e' : '#c62828').text(resp.msg || (resp.success ? 'Sent.' : 'Could not send.'));
                })
                .fail(function () {
                    $status.css('color', '#c62828').text('Could not send. Try again.');
                })
                .always(function () {
                    $('.bfc-send-receipt').prop('disabled', false);
                });
        });

        // "Stored in the back" on the next-step box saves the same storage location.
        $('#bfc_next_storage_save').on('click', function () {
            var val = $.trim($('#bfc_next_storage').val());
            if (!val) { $('#bfc_next_storage_status').css('color', '#c62828').text('Write where you put it first.'); $('#bfc_next_storage').focus(); return; }
            $('#bfc_storage_location_input').val(val);
            $('#bfc_storage_location_save').click();
        });
        $('#bfc_next_storage').on('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); $('#bfc_next_storage_save').click(); } });
        $('#bfc_next_store_instead').on('click', function (e) {
            e.preventDefault();
            var $i = $('#bfc_storage_location_input');
            $('html, body').animate({ scrollTop: $i.offset().top - 120 }, 200);
            $i.focus();
        });

        $('#bfc_storage_location_save').on('click', function () {
            var $btn = $(this);
            var $status = $('#bfc_storage_location_status');
            var val = $('#bfc_storage_location_input').val();
            $btn.prop('disabled', true);
            $status.text('Saving...');
            $.post('{{ route('buy-from-customer.storage-location.update', $offer->id) }}', {
                _token: $('meta[name="csrf-token"]').attr('content'),
                storage_location: val,
            })
                .done(function (resp) {
                    $('#bfc_storage_location_display').text(resp.storage_location || 'Not yet assigned');
                    $('#bfc_storage_location_meta').text(resp.updated_at ? ('Last set ' + resp.updated_at + (resp.updated_by ? ' by ' + resp.updated_by : '')) : '');
                    $status.text('Saved.');
                    if (resp.storage_location) {
                        $('#bfc_next_storage_status').css('color', '#1e8e3e').html('<i class="fa fa-check"></i> Stored at: ' + $('<div>').text(resp.storage_location).html());
                    }
                })
                .fail(function () {
                    $status.text('Save failed — try again.');
                })
                .always(function () {
                    $btn.prop('disabled', false);
                });
        });
    });
})();
</script>
@endsection
