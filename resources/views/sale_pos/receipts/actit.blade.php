{{--
    Bilingual (English / Arabic) A4 Tax Invoice — "actit" layout.
    Follows the "Tax Invoice / فاتورة ضريبية" From / To sheet:
    invoice info + QR, From / To party box, items grid, total amounts, bank details,
    and the letter-head footer strip (Invoice Layout > Footer text).

    Data sources (see TransactionUtil::getReceiptDetails):
      - PO Number      = sell custom field 1
      - Job No         = sell custom field 2
      - Payment Terms  = sale pay term
      - CR Number      = business Tax Number 2
      - Bank details   = invoice layout Sub heading lines 1-5
                         (Account Holder, Bank Name, Account Number, IBAN, SWIFT)
      - Building No    = contact / location custom field 2

    Developed by
    Md Saiful Islam (8801916665832)
    Full Stack Web Developer, Based in Bangladesh.
    Email : saiful.rana@gmail.com
    Github : https://github.com/saifulislam07
--}}

@php
    $buyer = (array) ($receipt_details->buyer ?? []);
    $seller = (array) ($receipt_details->seller ?? []);
    $meta = (array) ($receipt_details->sell_meta ?? []);
    $cur = trim($meta['currency_code'] ?? '') ?: trim($receipt_details->currency_symbol ?? '');

    $nf = fn($v) => number_format((float) $v, 4, '.', ',');
    $ar_digits = fn($s) => str_replace("-", "\u{200E}-\u{200E}", strtr((string) $s, ['0' => '٠', '1' => '١', '2' => '٢', '3' => '٣', '4' => '٤', '5' => '٥', '6' => '٦', '7' => '٧', '8' => '٨', '9' => '٩']));

    $ac_invoice_no = (string) $receipt_details->invoice_no;

    $ac_date_en = $receipt_details->invoice_date ?? '';
    $ac_date_ar = '';
    if (!empty($meta['transaction_date'])) {
        $d = \Carbon\Carbon::parse($meta['transaction_date']);
        $ac_date_en = $d->format('d-m-Y');
        $ac_date_ar = $ar_digits($d->format('Y-m-d'));
    }

    // ---------- totals computed from the lines so the table & summary reconcile ----------
    $ac_excl = 0;
    $ac_tax = 0;
    foreach ($receipt_details->lines as $l) {
        $ac_excl += $l['line_total_exc_tax_uf'] ?? 0;
        $ac_tax += ($l['tax_unformatted'] ?? 0) * ($l['quantity_uf'] ?? 0);
    }
    $ac_grand =
        isset($receipt_details->total_unformatted) && $receipt_details->total_unformatted !== null && $receipt_details->total_unformatted !== ''
            ? (float) $receipt_details->total_unformatted
            : $ac_excl + $ac_tax;
    // invoice-level discount is already netted out of the grand total
    $ac_taxable = $ac_grand - $ac_tax;

    $ac_words = trim($receipt_details->total_in_words ?? '');
    if ($ac_words === '') {
        $ac_words = (string) app(\App\Utils\Util::class)->numToWord(round($ac_grand, 2), 'en');
    }
    $ac_cur_word = ['SAR' => 'Riyal', 'AED' => 'Dirham', 'USD' => 'Dollar', 'BDT' => 'Taka'][strtoupper($meta['currency_code'] ?? '')] ?? ($meta['currency_code'] ?? '');
    $ac_words = $ac_words !== '' ? ucfirst($ac_words) . ($ac_cur_word !== '' ? ' ' . $ac_cur_word : '') : '';

    $ac_party_rows = function ($p, $is_seller) use ($meta) {
        $rows = [
            ['Name', $p['name'] ?? ''],
            ['Building No', $p['custom_field2'] ?? ''],
            ['Street Name', $p['street'] ?? ''],
            ['District', ($p['district'] ?? '') ?: ($p['state'] ?? '')],
            ['City', $p['city'] ?? ''],
            ['Country', $p['country'] ?? ''],
            ['Postal Code', $p['zip_code'] ?? ''],
            ['VAT Number', $p['tax_number'] ?? ''],
        ];
        if ($is_seller) {
            $rows[] = ['CR Number', $meta['cr_number'] ?? ''];
        }
        return $rows;
    };

    $ac_bank = array_filter((array) ($meta['bank'] ?? []), fn($v) => trim((string) $v) !== '');
@endphp

{{--
    NOTE: colors are declared !important because Bootstrap's print stylesheet forces
    "* { color:#000!important; background:0 0!important }" when printing.
    Only <td> borders print reliably in this app's print context, so every line is a cell border.
--}}
<style type="text/css">
    @page {
        size: A4 portrait;
        margin: 8mm;
    }

    @media print {
        html,
        body {
            height: auto !important;
            min-height: 0 !important;
            margin: 0 !important;
            padding: 0 !important;
            overflow: visible !important;
            background: #fff !important;
        }

        /* every ancestor of the sheet: unwrap flex, scroll containers, fixed heights */
        body :has(.ac-sheet) {
            display: block !important;
            width: auto !important;
            height: auto !important;
            min-height: 0 !important;
            max-height: none !important;
            margin: 0 !important;
            padding: 0 !important;
            border: 0 !important;
            position: static !important;
            float: none !important;
            overflow: visible !important;
            transform: none !important;
            background: #fff !important;
        }

        body *:not(:has(.ac-sheet)):not(.ac-sheet):not(.ac-sheet *) {
            display: none !important;
        }

        .main-sidebar,
        .sidebar,
        .main-header,
        .navbar,
        #app,
        .scrolltop,
        .modal,
        .modal-backdrop,
        .overlay,
        #toast-container,
        .main-footer,
        audio {
            display: none !important;
        }

        .ac-sheet {
            max-width: none !important;
        }

        /* fixed page height so the footer strip sits at the bottom of the A4 sheet;
           kept a little under the 281mm usable height to never spill onto a 2nd page */
        .ac-wrap {
            min-height: 272mm !important;
        }

        .ac-sheet>tbody>tr>td {
            page-break-after: avoid !important;
        }
    }

    .ac-sheet,
    .ac-sheet * {
        box-sizing: border-box;
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
    }

    .ac-sheet {
        width: 100%;
        max-width: 794px;
        table-layout: fixed;
        margin: 0 auto;
        border-collapse: collapse;
        color: #000 !important;
        background: #fff !important;
        font-family: 'Arial', 'Tahoma', 'DejaVu Sans', sans-serif;
        font-size: 10.5px;
        line-height: 1.35;
    }

    .ac-sheet>tbody>tr>td {
        padding: 0;
        border: 0;
        vertical-align: top;
    }

    .ac-wrap {
        min-height: 1080px;
        display: flex;
        flex-direction: column;
    }

    .ac-wrap>* {
        flex-shrink: 0;
    }

    .ac-wrap table {
        width: 100%;
        border-collapse: collapse;
    }

    .ac-ar {
        direction: rtl;
        unicode-bidi: embed;
    }

    .ac-c {
        text-align: center;
    }

    .ac-r {
        text-align: right;
    }

    .ac-b {
        font-weight: 700;
    }

    /* ---------- letter head ---------- */
    .ac-letterhead td {
        padding: 0;
        text-align: center;
    }

    .ac-letterhead img {
        display: block;
        width: 100%;
        height: auto;
        max-height: 120px;
        object-fit: contain;
    }

    /* ---------- title ---------- */
    .ac-title {
        text-align: center;
        font-size: 22px;
        font-weight: 700;
        margin: 4px 0 10px;
        line-height: 1.2;
    }

    /* ---------- info + qr ---------- */
    .ac-top>tbody>tr>td {
        vertical-align: top;
        padding: 0;
    }

    .ac-info td {
        border: 1px solid #000 !important;
        padding: 0 5px;
        height: 24px;
        font-size: 11px;
        white-space: nowrap;
        vertical-align: middle;
    }

    .ac-info .ac-lbl {
        font-weight: 700;
        font-size: 12px;
        white-space: nowrap;
    }

    .ac-info .ac-albl {
        font-weight: 700;
        text-align: right;
        direction: rtl;
        white-space: nowrap;
    }

    .ac-qr {
        text-align: right;
        line-height: 0;
    }

    .ac-qr img {
        width: 128px;
        height: 128px;
        max-width: none !important;
        display: inline-block;
    }

    /* ---------- from / to ---------- */
    .ac-party {
        margin-top: 18px;
    }

    .ac-party td {
        border-left: 1px solid #000 !important;
        border-right: 1px solid #000 !important;
        width: 50%;
        vertical-align: top;
    }

    .ac-party .ac-band td {
        background: #808080 !important;
        color: #fff !important;
        border: 1px solid #555 !important;
        padding: 6px 18px;
        font-weight: 700;
        font-size: 11px;
    }

    .ac-party .ac-band td span {
        color: #fff !important;
    }

    .ac-party .ac-body td {
        border-bottom: 1px solid #000 !important;
        padding: 6px 18px 10px;
        font-size: 11px;
        line-height: 1.45;
    }

    /* ---------- items ---------- */
    .ac-items {
        margin-top: 12px;
        border-collapse: separate !important;
        border-spacing: 0 !important;
        border: 0 !important;
    }

    .ac-items th,
    .ac-items td {
        border-top: 1px solid #000 !important;
        border-left: 1px solid #000 !important;
        padding: 4px 5px;
        font-size: 10.5px;
        vertical-align: middle;
    }

    .ac-items tr>th:last-child,
    .ac-items tr>td:last-child {
        border-right: 1px solid #000 !important;
    }

    .ac-items tbody tr:last-child>td {
        border-bottom: 1px solid #000 !important;
    }

    .ac-items thead {
        display: table-header-group;
    }

    .ac-items thead th {
        background: #808080 !important;
        color: #fff !important;
        font-weight: 400;
        text-align: center;
        line-height: 1.5;
        padding: 6px 4px;
        border-color: #555 !important;
    }

    .ac-items thead th span {
        color: #fff !important;
    }

    .ac-items thead th .ac-ar {
        display: block;
        font-size: 9.5px;
    }

    .ac-items tbody td {
        height: 26px;
        line-height: 1.45;
    }

    .ac-items tbody tr {
        page-break-inside: avoid;
    }

    /* ---------- total amounts ---------- */
    .ac-totals {
        margin-top: 12px;
        page-break-inside: avoid;
    }

    .ac-totals td {
        border: 1px solid #000 !important;
        padding: 4px 6px;
        height: 24px;
        font-size: 12px;
        vertical-align: middle;
    }

    .ac-totals .ac-band td {
        background: #808080 !important;
        color: #fff !important;
        border-color: #555 !important;
        font-weight: 700;
        font-size: 12.5px;
        height: 30px;
    }

    .ac-totals .ac-band td span {
        color: #fff !important;
    }

    .ac-totals .ac-ten {
        font-weight: 700;
    }

    .ac-totals .ac-tar {
        font-weight: 700;
        text-align: right;
        direction: rtl;
    }

    .ac-totals .ac-tv {
        text-align: right;
        font-size: 11px;
        white-space: nowrap;
    }

    .ac-totals .ac-words td {
        font-weight: 700;
        font-size: 12.5px;
    }

    /* ---------- bank ---------- */
    .ac-bank {
        margin-top: 12px;
        width: 50% !important;
        page-break-inside: avoid;
    }

    .ac-bank td {
        border: 1px solid #000 !important;
        padding: 0 6px;
        height: 25px;
        font-size: 11px;
        vertical-align: middle;
    }

    .ac-bank .ac-lbl {
        width: 38%;
        font-weight: 700;
        font-size: 12.5px;
    }

    /* ---------- spacer + footer strip ---------- */
    .ac-spacer {
        flex: 1 1 auto;
        min-height: 30px;
    }

    .ac-foot>tbody>tr>td {
        border-top: 1px solid #000 !important;
        padding: 10px 10px 0;
        font-size: 13px;
    }

    .ac-foot td td {
        border: 0 !important;
        padding: 0 6px;
        vertical-align: top;
        font-size: 12px !important;
    }

    .ac-foot p {
        margin: 0;
    }
</style>

<table class="ac-sheet">
    <tr>
        <td>
            <div class="ac-wrap">

                {{-- ================= LETTER HEAD ================= --}}
                @if (!empty($receipt_details->letter_head))
                    <table class="ac-letterhead">
                        <tr>
                            <td><img src="{{ $receipt_details->letter_head }}" alt="letter head"></td>
                        </tr>
                    </table>
                @endif

                {{-- ================= TITLE ================= --}}
                <div class="ac-title" dir="ltr">
                    {{ ($receipt_details->invoice_heading ?? '') ?: 'Tax Invoice' }}
                    <span class="ac-ar">فاتورة ضريبية</span>
                </div>

                {{-- ================= INFO | QR ================= --}}
                <table class="ac-top">
                    <tr>
                        <td style="width:48%;">
                            <table class="ac-info">
                                <tr>
                                    <td class="ac-lbl" style="width:33%;">Invoice Number</td>
                                    <td style="width:18%;">{{ $ac_invoice_no }}</td>
                                    <td style="width:17%;" dir="ltr">{{ $ar_digits($ac_invoice_no) }}</td>
                                    <td class="ac-albl" style="width:32%;">رقم الفاتورة</td>
                                </tr>
                                <tr>
                                    <td class="ac-lbl">Invoice Issue Date</td>
                                    <td>{{ $ac_date_en }}</td>
                                    <td dir="ltr">{{ $ac_date_ar }}</td>
                                    <td class="ac-albl">تاريخ إصدار الفاتورة</td>
                                </tr>
                                <tr>
                                    <td class="ac-lbl" colspan="2">PO Number</td>
                                    <td colspan="2">{{ $meta['po_number'] ?? '' }}</td>
                                </tr>
                                <tr>
                                    <td class="ac-lbl" colspan="2">Payment Terms</td>
                                    <td colspan="2">{{ $meta['payment_terms'] ?? '' }}</td>
                                </tr>
                                <tr>
                                    <td class="ac-lbl" colspan="2">Job No</td>
                                    <td colspan="2">{{ $meta['job_no'] ?? '' }}</td>
                                </tr>
                            </table>
                        </td>
                        <td class="ac-qr">
                            @if (!empty($receipt_details->show_qr_code) && !empty($receipt_details->qr_code_text))
                                <img src="data:image/png;base64,{{ DNS2D::getBarcodePNG($receipt_details->qr_code_text, 'QRCODE', 4, 4, [0, 0, 0]) }}"
                                    alt="qr">
                            @endif
                        </td>
                    </tr>
                </table>

                {{-- ================= FROM / TO ================= --}}
                <table class="ac-party">
                    <tr class="ac-band">
                        <td>
                            <span style="float:right;" class="ac-ar">من</span>
                            <span>From</span>
                        </td>
                        <td>
                            <span style="float:right;" class="ac-ar">إلى</span>
                            <span>To</span>
                        </td>
                    </tr>
                    <tr class="ac-body">
                        <td>
                            <div class="ac-b">Company Details</div>
                            @foreach ($ac_party_rows($seller, true) as $row)
                                <div>{{ $row[0] }}: {{ $row[1] }}</div>
                            @endforeach
                        </td>
                        <td>
                            <div class="ac-b">Client Details</div>
                            @foreach ($ac_party_rows($buyer, false) as $row)
                                <div>{{ $row[0] }}: {{ $row[1] }}</div>
                            @endforeach
                        </td>
                    </tr>
                </table>

                {{-- ================= LINE ITEMS ================= --}}
                <table class="ac-items">
                    <thead>
                        <tr>
                            <th style="width:6%;">Sl No<span class="ac-ar">الرقم التسلسلي</span></th>
                            <th style="width:30%;">Description<span class="ac-ar">وصف</span></th>
                            <th style="width:9%;">Unit Price<span class="ac-ar">الوحدة سعر</span></th>
                            <th style="width:9%;">Quantity<span class="ac-ar">الكمية</span></th>
                            <th style="width:11%;">Taxable Amount<span class="ac-ar">الوعاء الضريبي</span></th>
                            <th style="width:7%;">Tax Rate<span class="ac-ar">نسبة الضريبة</span></th>
                            <th style="width:11%;">Tax Amount<span class="ac-ar">مبلغ الضريبة</span></th>
                            <th style="width:17%;">Item Subtotal<br>(Including VAT)<span class="ac-ar">المجموع الفرعي للعنصر</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($receipt_details->lines as $idx => $line)
                            @php
                                $q = $line['quantity_uf'] ?? 0;
                                $taxable = $line['line_total_exc_tax_uf'] ?? 0;
                                $unit_price = $line['unit_price_before_discount_uf'] ?? ($line['unit_price_uf'] ?? 0);
                                $line_tax = ($line['tax_unformatted'] ?? 0) * $q;
                                $rate = $line['tax_percent'];
                            @endphp
                            <tr>
                                <td class="ac-c">{{ $idx + 1 }}</td>
                                <td>
                                    {{ $line['name'] }} {{ $line['product_variation'] }} {{ $line['variation'] }}
                                    @if (!empty($line['product_custom_fields']))
                                        <br>{{ $line['product_custom_fields'] }}
                                    @endif
                                    @if (!empty($line['sell_line_note']))
                                        <br>{!! nl2br(e(strip_tags($line['sell_line_note']))) !!}
                                    @endif
                                </td>
                                <td class="ac-c">{{ $nf($unit_price) }}</td>
                                <td class="ac-r">{{ $q + 0 }} {{ $line['units'] ?? '' }}</td>
                                <td class="ac-c">{{ $nf($taxable) }}</td>
                                <td class="ac-c">
                                    @if ($rate !== null && $rate !== '')
                                        {{ $rate + 0 }}%
                                    @endif
                                </td>
                                <td class="ac-c">{{ $nf($line_tax) }}</td>
                                <td class="ac-r">{{ $nf($taxable + $line_tax) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>

                {{-- ================= TOTAL AMOUNTS ================= --}}
                <table class="ac-totals">
                    <tr class="ac-band">
                        <td colspan="3">Total amounts:</td>
                        <td class="ac-r ac-ar">إجمالي المبالغ :</td>
                    </tr>
                    <tr>
                        <td style="width:13%;"></td>
                        <td class="ac-ten" style="width:33%;">Total (Excluding VAT):</td>
                        <td class="ac-tar" style="width:33%;">مبلغ الخاضع للضريبة (غير شامل ضريبة القيمة المضافة)</td>
                        <td class="ac-tv" style="width:21%;">{{ $nf($ac_excl) }} {{ $cur }}</td>
                    </tr>
                    <tr>
                        <td></td>
                        <td class="ac-ten">Total Taxable Amount (Excluding VAT):</td>
                        <td class="ac-tar">إجمالي المبلغ الخاضع للضريبة (باستثناء ضريبة القيمة المضافة)</td>
                        <td class="ac-tv">{{ $nf($ac_taxable) }} {{ $cur }}</td>
                    </tr>
                    <tr>
                        <td></td>
                        <td class="ac-ten">Total VAT:</td>
                        <td class="ac-tar">إجمالي ضريبة القيمة المضافة</td>
                        <td class="ac-tv">{{ $nf($ac_tax) }} {{ $cur }}</td>
                    </tr>
                    <tr>
                        <td></td>
                        <td class="ac-ten">Total Including VAT</td>
                        <td class="ac-tar">الاجمالي شامل ضريبة القيمة المضافة</td>
                        <td class="ac-tv">{{ $nf($ac_grand) }} {{ $cur }}</td>
                    </tr>
                    <tr class="ac-words">
                        <td colspan="4">Amount In Words: {{ $ac_words }}</td>
                    </tr>
                </table>

                {{-- ================= BANK DETAILS ================= --}}
                @if (!empty($ac_bank))
                    <table class="ac-bank">
                        @foreach ($ac_bank as $label => $value)
                            <tr>
                                <td class="ac-lbl">{{ $label }}</td>
                                <td>{{ $value }}</td>
                            </tr>
                        @endforeach
                    </table>
                @endif

                {{-- ================= FOOTER STRIP (Invoice Layout > Footer text) ================= --}}
                <div class="ac-spacer">&nbsp;</div>
                @if (!empty($receipt_details->footer_text))
                    <table class="ac-foot">
                        <tr>
                            <td>{!! $receipt_details->footer_text !!}</td>
                        </tr>
                    </table>
                @endif

            </div>
        </td>
    </tr>
</table>
