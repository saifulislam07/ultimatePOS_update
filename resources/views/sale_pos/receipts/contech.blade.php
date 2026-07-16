{{--
    CONTECH style Tax Invoice layout (Odoo-light look).
    Layout matches the reference design; all data comes from $receipt_details.
--}}
@php
    // ---------- palette (fixed, matches reference) ----------
    $page_bg     = '#f5efe4';   // cream canvas the white invoice sheet sits on
    $peach       = '#fcebe0';   // header / title band / grand total bg
    $maroon      = '#8b3a2e';   // "Tax Invoice" title + grand total text
    $teal        = '#0e565c';   // company name (top right)
    $ink         = '#443c39';   // main text
    $soft_ink    = '#6b5a52';   // header address text
    $totals_bg   = '#f1f1f1';   // totals rows bg
    $orange      = '#e97c4f';   // footer line + footer text

    // ---------- totals computed from lines so table & summary reconcile ----------
    $ct_taxable = 0;
    $ct_vat     = 0;
    foreach ($receipt_details->lines as $l) {
        $ct_taxable += $l['line_total_exc_tax_uf'] ?? 0;
        $ct_vat     += (($l['tax_unformatted'] ?? 0) * ($l['quantity_uf'] ?? 0));
    }
    $ct_gross   = number_format($ct_taxable + $ct_vat, 2, '.', '');
    $ct_taxable = number_format($ct_taxable, 2, '.', '');
    $ct_vat     = number_format($ct_vat, 2, '.', '');

    // ---------- server-side currency formatter (JS display_currency doesn't run in pdf/print) ----------
    $ct_symbol    = $receipt_details->currency_symbol ?? (session('currency')['symbol'] ?? '');
    $ct_placement = session('business.currency_symbol_placement', 'before');
    $ct_money = function ($amount) use ($ct_symbol, $ct_placement) {
        if ($ct_symbol === '') {
            return $amount;
        }
        return $ct_placement == 'after' ? $amount.' '.$ct_symbol : $ct_symbol.' '.$amount;
    };

    // ---------- meta columns: Invoice Date, Due Date + sell custom fields ----------
    $ct_meta   = [];
    $ct_meta[] = [(($receipt_details->date_label ?? '') ?: 'Invoice Date'), $receipt_details->invoice_date ?? ''];
    if (!empty($receipt_details->due_date)) {
        $ct_meta[] = [(($receipt_details->due_date_label ?? '') ?: 'Due Date'), $receipt_details->due_date];
    }
    foreach (['1', '2', '3', '4'] as $i) {
        $val = $receipt_details->{'sell_custom_field_'.$i.'_value'} ?? '';
        if (!empty($val)) {
            $ct_meta[] = [$receipt_details->{'sell_custom_field_'.$i.'_label'} ?? '', $val];
        }
    }
@endphp

{{--
    NOTE: every color/background is declared !important on purpose.
    Bootstrap's print stylesheet (vendor.css) contains
    "@media print { * { color:#000!important; background:0 0!important } }"
    which would otherwise wipe all invoice colors when printing.
--}}
<style type="text/css">
    .ct-page { background: {{ $page_bg }} !important; padding: 32px 16px;
        -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
    .contech-invoice { max-width: 880px; margin: 0 auto; background: #fff !important; color: {{ $ink }} !important;
        font-family: 'Lato', 'Helvetica Neue', Helvetica, Arial, sans-serif; font-size: 13px; line-height: 1.5;
        -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
    .contech-invoice * { box-sizing: border-box; -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
    .contech-invoice table { width: 100%; border-collapse: collapse; }
    .contech-invoice .ct-right { text-align: right; }
    .contech-invoice .ct-center { text-align: center; }
    .contech-invoice .ct-b { font-weight: 700; }

    /* ---------- peach header band ---------- */
    .contech-invoice .ct-header { background: {{ $peach }} !important; padding: 6px 28px 2px; }
    .contech-invoice .ct-logobox { display: inline-block; background: #fff !important; border-radius: 12px; padding: 10px 18px; }
    .contech-invoice .ct-logo { max-height: 78px; max-width: 220px; width: auto; display: block; }
    .contech-invoice .ct-logo-name { color: {{ $maroon }} !important; font-size: 26px; font-weight: 800; letter-spacing: 1px; }
    .contech-invoice .ct-company { color: {{ $teal }} !important; font-size: 14px; font-weight: 700; margin-bottom: 2px; }
    .contech-invoice .ct-h-meta { color: {{ $soft_ink }} !important; font-size: 11.5px; line-height: 1.6; }
    .contech-invoice .ct-h-meta * { color: {{ $soft_ink }} !important; }

    /* ---------- title band: white sheet cuts up into the peach with a diagonal left edge ---------- */
    .contech-invoice .ct-titlewrap { background: {{ $peach }} !important; text-align: right; margin: 0; padding: 8px 0 0; }
    .contech-invoice .ct-titleband { display: inline-block; vertical-align: bottom; background: #fff !important;
        clip-path: polygon(64px 0, 100% 0, 100% 100%, 0 100%);
        padding: 14px 28px 10px 96px; min-width: 58%; text-align: right; }
    .contech-invoice .ct-title { color: {{ $maroon }} !important; font-size: 26px; font-weight: 700; margin: 0; white-space: nowrap; }

    .contech-invoice .ct-body { padding: 0 28px; }

    /* ---------- customer ---------- */
    .contech-invoice .ct-customer { padding: 12px 0 18px; line-height: 1.55; color: {{ $ink }} !important; }
    .contech-invoice .ct-customer .ct-cust-name { text-transform: uppercase; }

    /* ---------- meta row ---------- */
    .contech-invoice .ct-meta td { padding: 0 14px 4px 0; vertical-align: top; }
    .contech-invoice .ct-meta-label { color: {{ $maroon }} !important; font-size: 12px; font-weight: 700; padding-bottom: 4px; }
    .contech-invoice .ct-meta-value { color: {{ $ink }} !important; font-size: 12.5px; }

    /* ---------- items table ---------- */
    .contech-invoice .ct-items { margin-top: 22px; }
    .contech-invoice .ct-items thead td { color: #6e6e6e !important; font-size: 11px; font-weight: 400; padding: 6px 8px;
        border-bottom: 1px solid #4a4a4a !important; vertical-align: bottom; line-height: 1.35; background: #fff !important; }
    .contech-invoice .ct-items tbody td { padding: 11px 8px; vertical-align: top; font-size: 12px; border: none; color: {{ $ink }} !important; background: #fff !important; }
    .contech-invoice .ct-items .ct-sub { color: #8a8a8a !important; font-size: 10.5px; }

    /* ---------- bottom: terms + qr | totals ---------- */
    .contech-invoice .ct-foot { margin-top: 20px; page-break-inside: avoid; }
    .contech-invoice .ct-terms { line-height: 2; font-size: 12.5px; color: {{ $ink }} !important; }
    .contech-invoice .ct-qr { margin-top: 14px; }
    .contech-invoice .ct-qr img { width: 150px; height: 150px; }

    .contech-invoice .ct-totals td { padding: 10px 14px; font-size: 12.5px; color: {{ $ink }} !important; }
    .contech-invoice .ct-totals .ct-t1 td { background: {{ $totals_bg }} !important; }
    .contech-invoice .ct-totals .ct-t2 td { background: #fff !important; border-top: 1px solid #ececec !important; border-bottom: 1px solid #ececec !important; }
    .contech-invoice .ct-totals .ct-grand td { background: {{ $peach }} !important; color: {{ $maroon }} !important; font-weight: 700; font-size: 13px; }

    /* ---------- page footer ---------- */
    .contech-invoice .ct-pagefoot { border-top: 2px solid {{ $orange }} !important; margin: 34px 28px 0; padding-top: 8px; }
    .contech-invoice .ct-pagefoot .ct-contact { color: {{ $orange }} !important; font-size: 12px; }
    .contech-invoice .ct-pagefoot .ct-datecell { color: #8a8a8a !important; font-size: 11px; text-align: right; white-space: nowrap; }
</style>

<div class="ct-page">
<div class="contech-invoice">

    {{-- ================= HEADER (peach band) ================= --}}
    <div class="ct-header">
        <table>
            <tr>
                <td style="width:48%; vertical-align: top;">
                    @if(!empty($receipt_details->logo))
                        <img class="ct-logo" src="{{ $receipt_details->logo }}" alt="logo">
                    @else
                        <div class="ct-logo-name">{{ $receipt_details->display_name ?? '' }}</div>
                    @endif
                </td>
                <td style="width:52%; vertical-align: top;" class="ct-right">
                    @if(!empty($receipt_details->header_text))
                        <div class="ct-company">{!! $receipt_details->header_text !!}</div>
                    @elseif(!empty($receipt_details->display_name) && !empty($receipt_details->logo))
                        <div class="ct-company">{{ $receipt_details->display_name }}</div>
                    @endif

                    <div class="ct-h-meta">
                        @php
                            $sub_headings = array_filter([
                                $receipt_details->sub_heading_line1 ?? '',
                                $receipt_details->sub_heading_line2 ?? '',
                                $receipt_details->sub_heading_line3 ?? '',
                                $receipt_details->sub_heading_line4 ?? '',
                                $receipt_details->sub_heading_line5 ?? '',
                            ]);
                        @endphp
                        @if(!empty($sub_headings))
                            {!! implode('<br>', $sub_headings) !!}<br><br>
                        @endif

                        @if(!empty($receipt_details->code_1))
                            {{ $receipt_details->code_label_1 ?? '' }} : {{ $receipt_details->code_1 }}<br>
                        @endif

                        @if(!empty($receipt_details->tax_info1))
                            {{ $receipt_details->tax_label1 ?? '' }}{{ $receipt_details->tax_info1 }}
                        @endif
                    </div>
                </td>
            </tr>
        </table>
    </div>

    {{-- ================= TITLE BAND ================= --}}
    <div class="ct-titlewrap">
        <div class="ct-titleband">
            <h1 class="ct-title">
                {{ ($receipt_details->invoice_heading ?? '') ?: 'Tax Invoice' }}
                @if(!empty($receipt_details->invoice_no_prefix)){!! $receipt_details->invoice_no_prefix !!}@endif{{ $receipt_details->invoice_no }}
            </h1>
        </div>
    </div>

    <div class="ct-body">

        {{-- ================= CUSTOMER ================= --}}
        <div class="ct-customer">
            @php
                // customer_info already starts with the customer name (via contact_address);
                // strip it so the name only appears once, in the styled line above.
                $ct_cust_info = $receipt_details->customer_info ?? '';
                $ct_cust_name = trim(strip_tags($receipt_details->customer_name ?? ''));
                if ($ct_cust_name !== '' && $ct_cust_info !== '') {
                    $ct_q = preg_quote($ct_cust_name, '/');
                    $ct_cust_info = preg_replace('/^\s*'.$ct_q.'\s*,?\s*(?:<br\s*\/?>)?/u', '', $ct_cust_info, 1, $ct_replaced);
                    if (empty($ct_replaced)) {
                        $ct_cust_info = preg_replace('/,?\s*(?:<br\s*\/?>)?\s*'.$ct_q.'/u', '', $ct_cust_info, 1);
                    }
                    $ct_cust_info = trim($ct_cust_info);
                }
            @endphp
            @if(!empty($receipt_details->customer_name))
                <div class="ct-cust-name">{{ $receipt_details->customer_name }}</div>
            @endif
            @if(!empty($receipt_details->customer_custom_fields))
                {!! $receipt_details->customer_custom_fields !!}<br>
            @endif
            @if(!empty($ct_cust_info))
                {!! $ct_cust_info !!}<br>
            @endif
            @if(!empty($receipt_details->customer_tax_number))
                {{ ($receipt_details->customer_tax_label ?? '') ?: 'VAT Number:' }} {{ $receipt_details->customer_tax_number }}
            @endif
        </div>

        {{-- ================= META ROW (dates / source / reference) ================= --}}
        <table class="ct-meta">
            <tr>
                @foreach($ct_meta as $m)
                    <td>
                        <div class="ct-meta-label">{{ $m[0] }}</div>
                        <div class="ct-meta-value">{!! $m[1] !!}</div>
                    </td>
                @endforeach
            </tr>
        </table>

        {{-- ================= LINE ITEMS ================= --}}
        <table class="ct-items">
            <thead>
                <tr>
                    <td style="width:30%;">{{ ($receipt_details->table_product_label ?? '') ?: 'Description' }}</td>
                    <td class="ct-right" style="width:9%;">{{ ($receipt_details->table_qty_label ?? '') ?: 'Quantity' }}</td>
                    <td class="ct-right" style="width:10%;">{{ ($receipt_details->table_unit_price_label ?? '') ?: 'Unit Price' }}</td>
                    <td class="ct-center" style="width:11%;">Taxes</td>
                    <td class="ct-right" style="width:11%;">VAT<br>Amount</td>
                    <td class="ct-right" style="width:14.5%;">Subtotal<br>(Exclusive of<br>VAT)</td>
                    <td class="ct-right" style="width:14.5%;">Subtotal<br>(Inclusive of VAT)</td>
                </tr>
            </thead>
            <tbody>
                @foreach($receipt_details->lines as $line)
                    @php
                        $line_vat   = ($line['tax_unformatted'] ?? 0) * ($line['quantity_uf'] ?? 0);
                        $line_gross = ($line['line_total_exc_tax_uf'] ?? 0) + $line_vat;
                        $line_vat   = number_format($line_vat, 2, '.', '');
                        $line_gross = number_format($line_gross, 2, '.', '');
                        $line_exc   = number_format($line['line_total_exc_tax_uf'] ?? 0, 2, '.', '');
                        // unit price recomputed from raw values so it always shows 2 decimals
                        // even when business currency precision is 3-4
                        $line_unit  = ($line['quantity_uf'] ?? 0) > 0
                            ? ($line['line_total_exc_tax_uf'] ?? 0) / $line['quantity_uf']
                            : 0;
                        $line_unit  = number_format($line_unit, 2, '.', '');
                    @endphp
                    <tr>
                        <td>
                            @if(!empty($line['sub_sku'])){{ $line['sub_sku'] }} @endif{{ $line['name'] }} {{ $line['product_variation'] }} {{ $line['variation'] }}
                            @if(!empty($line['product_custom_fields']))<br><span class="ct-sub">{{ $line['product_custom_fields'] }}</span>@endif
                            @if(!empty($line['sell_line_note']))<br><span class="ct-sub">{!! $line['sell_line_note'] !!}</span>@endif
                        </td>
                        <td class="ct-right" style="white-space: nowrap;">{{ $line['quantity'] }} {{ $line['units'] }}</td>
                        <td class="ct-right" style="white-space: nowrap;">
                            {{ $ct_money($line_unit) }}
                        </td>
                        <td class="ct-center">
                            @if(!empty($line['tax_name']))
                                {{ $line['tax_name'] }}@if(!empty($line['tax_percent']) && strpos($line['tax_name'], '%') === false) {{ $line['tax_percent'] + 0 }}%@endif
                            @endif
                        </td>
                        <td class="ct-right" style="white-space: nowrap;">
                            {{ $ct_money($line_vat) }}
                        </td>
                        <td class="ct-right" style="white-space: nowrap;">
                            {{ $ct_money($line_exc) }}
                        </td>
                        <td class="ct-right" style="white-space: nowrap;">
                            {{ $ct_money($line_gross) }}
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        {{-- ================= TERMS + QR (left) | TOTALS (right) ================= --}}
        <table class="ct-foot">
            <tr>
                <td style="width:52%; vertical-align: top; padding-right: 30px;">
                    <div class="ct-terms">
                        @if(!empty($receipt_details->additional_notes))
                            {!! nl2br($receipt_details->additional_notes) !!}<br>
                        @endif
                        Payment Communication: <span class="ct-b">@if(!empty($receipt_details->invoice_no_prefix)){!! $receipt_details->invoice_no_prefix !!}@endif{{ $receipt_details->invoice_no }}</span>
                    </div>
                    @if(!empty($receipt_details->show_qr_code) && !empty($receipt_details->qr_code_text))
                        <div class="ct-qr">
                            <img src="data:image/png;base64,{{ DNS2D::getBarcodePNG($receipt_details->qr_code_text, 'QRCODE', 5, 5, [39, 48, 54]) }}" alt="qr">
                        </div>
                    @endif
                    @if(!empty($receipt_details->show_barcode))
                        <div class="ct-qr">
                            <img style="width:auto; height:auto;" src="data:image/png;base64,{{ DNS1D::getBarcodePNG($receipt_details->invoice_no, 'C128', 2, 30, [39, 48, 54], true) }}" alt="barcode">
                        </div>
                    @endif
                </td>
                <td style="width:48%; vertical-align: top;">
                    <table class="ct-totals">
                        <tr class="ct-t1">
                            <td>Invoice Taxable Amount</td>
                            <td class="ct-right" style="white-space: nowrap;">
                                {{ $ct_money($ct_taxable) }}
                            </td>
                        </tr>
                        <tr class="ct-t2">
                            <td>VAT Taxes</td>
                            <td class="ct-right" style="white-space: nowrap;">
                                {{ $ct_money($ct_vat) }}
                            </td>
                        </tr>
                        @if(!empty($receipt_details->shipping_charges))
                            <tr class="ct-t1">
                                <td>{{ $receipt_details->shipping_charges_label ?? '' }}</td>
                                <td class="ct-right">{{ $receipt_details->shipping_charges }}</td>
                            </tr>
                        @endif
                        @if(!empty($receipt_details->discount))
                            <tr class="ct-t2">
                                <td>{!! $receipt_details->discount_label ?? '' !!}</td>
                                <td class="ct-right">(-) {{ $receipt_details->discount }}</td>
                            </tr>
                        @endif
                        <tr class="ct-grand">
                            <td>Invoice Gross Total (Inclusive of VAT)</td>
                            <td class="ct-right" style="white-space: nowrap;">
                                @if(isset($receipt_details->total_unformatted) && $receipt_details->total_unformatted !== '' && $receipt_details->total_unformatted !== null)
                                    {{ $ct_money(number_format((float) $receipt_details->total_unformatted, 2, '.', '')) }}
                                @else
                                    {{ $ct_money($ct_gross) }}
                                @endif
                            </td>
                        </tr>
                        @if(!empty($receipt_details->total_in_words))
                            <tr>
                                <td colspan="2" class="ct-right" style="color:#8a8a8a;"><small>({{ $receipt_details->total_in_words }})</small></td>
                            </tr>
                        @endif
                    </table>
                </td>
            </tr>
        </table>
    </div>

    {{-- ================= PAGE FOOTER ================= --}}
    <div class="ct-pagefoot">
        <table>
            <tr>
                <td style="width:20%;"></td>
                <td class="ct-contact ct-center">
                    @if(!empty($receipt_details->footer_text))
                        {!! $receipt_details->footer_text !!}
                    @else
                        @php
                            $foot = array_filter([
                                strip_tags($receipt_details->website ?? ''),
                                strip_tags(str_replace(['<br>', '<br/>', '<br />'], ' | ', $receipt_details->contact ?? '')),
                            ]);
                        @endphp
                        {!! implode(' | ', $foot) !!}
                    @endif
                </td>
                <td class="ct-datecell" style="width:20%;">
                    {{ $receipt_details->invoice_date ?? '' }}<br>
                    Page 1 / 1
                </td>
            </tr>
        </table>
    </div>

</div>
</div>
