{{--
    Bilingual (Arabic / English) A4 Tax Invoice — "bamsa" layout.
    Pixel-follows the Faisal Moammed Omar Bamsa Co "فاتورة ضريبية / Tax Invoice" sheet.
    Tuned to fit a single A4 page.

    The band between the items table and the totals box is intentionally left
    EMPTY (see .bm-stamp) so a physical company stamp / seal can be placed there.

    All data comes from $receipt_details (see TransactionUtil::getReceiptDetails).

    Developed by 
    Md Saiful Islam (8801916665832) 
    Full Stack Web Developer, Based in Bangladesh.
    Email : saiful.rana@gmail.com 
    Github : https://github.com/saifulislam07
--}}


@php
    $buyer = (array) ($receipt_details->buyer ?? []);
    $seller = (array) ($receipt_details->seller ?? []);
    $sym = trim($receipt_details->currency_symbol ?? '');

    $nf = fn($v) => number_format((float) $v, 2, '.', ',');

    // ---------- totals computed from the lines so table & summary reconcile ----------
    $bm_taxable = 0;
    $bm_disc = 0;
    $bm_tax = 0;
    $bm_qty = 0;
    foreach ($receipt_details->lines as $l) {
        $q = $l['quantity_uf'] ?? 0;
        $taxable = $l['line_total_exc_tax_uf'] ?? 0;
        $before = ($l['unit_price_before_discount_uf'] ?? ($l['unit_price_uf'] ?? 0)) * $q;
        $bm_qty += $q;
        $bm_taxable += $taxable;
        $bm_disc += max($before - $taxable, 0);
        $bm_tax += ($l['tax_unformatted'] ?? 0) * $q;
    }
    $bm_grand =
        isset($receipt_details->total_unformatted) &&
        $receipt_details->total_unformatted !== null &&
        $receipt_details->total_unformatted !== ''
            ? (float) $receipt_details->total_unformatted
            : $bm_taxable + $bm_tax;

    // rows for the buyer / seller boxes: [english label, value, arabic label]
    $bm_party_rows = function ($p, $is_seller) {
        return [
            [
                $is_seller ? 'Seller Name' : 'Client Name',
                $is_seller ? $p['legal_name'] ?? ($p['name'] ?? '') : $p['name'] ?? '',
                $is_seller ? 'اسم البائع' : 'اسم العميل'
            ],
            ['Vat No', $p['tax_number'] ?? '', 'الرقم الضريبي'],
            [$is_seller ? 'Country' : 'Country Code', $p['country'] ?? '', $is_seller ? 'الدولة' : 'رمز الدولة'],
            ['City', $p['city'] ?? '', 'المدينة'],
            ['Street Name', $p['street'] ?? '', 'اسم الشارع'],
            ['District', $p['district'] ?? ($p['state'] ?? ''), 'الحي'],
            ['Postal Code', $p['zip_code'] ?? '', 'الرمز البريدي'],
            ['Additional No', $p['custom_field1'] ?? '', 'الرقم الإضافي'],
            ['Building No', $p['custom_field2'] ?? '', 'رقم المبنى'],
            [
                $is_seller ? 'Contact US' : 'Client Mobile',
                $p['mobile'] ?? '' ?: $p['alternate_number'] ?? '',
                $is_seller ? 'للتواصل معنا' : 'هاتف العميل'
            ]
        ];
    };

    $printed_by = auth()->check()
        ? trim((auth()->user()->first_name ?? '') . ' ' . (auth()->user()->last_name ?? ''))
        : '';
@endphp

{{--
    NOTE: colors are declared !important because Bootstrap's print stylesheet forces
    "* { color:#000!important; background:0 0!important }" when printing.
--}}
<style type="text/css">
    @page {
        size: A4 portrait;
        margin: 5mm;
    }

    @media print {

        /* ---- collapse the whole app shell: only the receipt may occupy the page ----
           Do NOT hard-code where #receipt_section lives. The real DOM is

             <body> > div.tw-flex.thetop > main > #scrollable-container > section#receipt_section

           on list/POS pages, while layouts/app.blade.php ALSO keeps its own empty
           #receipt_section directly under <main>; jQuery fills whichever comes first.
           Path-based rules (body>*:not(main), main>*:not(#receipt_section)) hid one of
           the real ancestors and printed a blank page, so the ancestor chain is now
           selected dynamically from the sheet itself with :has(). */
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
        body :has(.bm-sheet) {
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

        /* anything that is neither the sheet, nor an ancestor of it, nor inside it.
           This also removes the leftover empty #receipt_section, which would otherwise
           add a trailing blank page via AdminLTE's .invoice border/padding. */
        body *:not(:has(.bm-sheet)):not(.bm-sheet):not(.bm-sheet *) {
            display: none !important;
        }

        /* Fallback for engines without :has() (both rules above are dropped there):
           hide the chrome explicitly. None of these can ever contain the sheet, so it
           is safe either way - worst case the invoice prints below the page content
           instead of alone, which still beats a blank sheet. */
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

        .bm-sheet {
            max-width: none !important;
        }

        .bm-sheet,
        .bm-sheet * {
            line-height: 1.25 !important;
        }

        /* nothing after the sheet may spill onto a second page */
        .bm-sheet>tbody>tr>td {
            page-break-after: avoid !important;
        }
    }

    /* The whole sheet sits inside ONE table cell, so the outer frame is a <td> border —
       the only border mechanism that prints reliably here. No div/table-element borders. */
    .bm-sheet,
    .bm-sheet * {
        box-sizing: border-box;
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
    }

    .bm-sheet {
        width: 100%;
        max-width: 780px;
        margin: 0 auto;
        border-collapse: collapse;
        color: #000 !important;
        font-family: 'Tahoma', 'DejaVu Sans', 'Arial', sans-serif;
        font-size: 10px;
        line-height: 1.3;
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
    }

    .bm-sheet>tbody>tr>td {
        border: 1px solid #000 !important;
        padding: 10px 12px 8px;
        vertical-align: top;
    }

    .bm-wrap {
        min-height: 1010px;
        overflow: hidden;
        display: flex;
        flex-direction: column;
    }

    /* In print the forced min-height is the #1 cause of a trailing blank page:
       if the printer's usable height is a little smaller than we assume, the sheet
       spills a sliver onto page 2. So drop it and give the stamp band a fixed size —
       the sheet then flows to its natural height, always inside one page. */
    @media print {
        .bm-wrap {
            min-height: 0 !important;
            height: auto !important;
        }

        .bm-stamp {
            flex: 0 0 auto !important;
            height: 150px !important;
            min-height: 0 !important;
        }
    }

    .bm-wrap>* {
        flex-shrink: 0;
    }

    .bm-wrap * {
        box-sizing: border-box;
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
    }

    .bm-wrap table {
        width: 100%;
        border-collapse: collapse;
    }

    .bm-ar {
        direction: rtl;
    }

    .bm-c {
        text-align: center;
    }

    .bm-r {
        text-align: right;
    }

    /* ---------- letter head (replaces the text header when uploaded) ---------- */
    .bm-letterhead td {
        padding: 0;
        border: 0 !important;
        text-align: center;
    }

    .bm-letterhead img {
        display: block;
        width: 100%;
        max-width: 100%;
        height: auto;
        max-height: 150px;
        object-fit: contain;
    }

    /* ---------- header ---------- */
    .bm-hdr td {
        vertical-align: middle;
        padding: 1px 4px;
    }

    .bm-hdr .bm-name {
        font-size: 15px;
        font-weight: 700;
        line-height: 1.2;
        white-space: nowrap;
    }

    .bm-hdr .bm-sub {
        font-size: 10.5px;
        letter-spacing: 1px;
    }

    .bm-hdr .bm-logobox {
        display: inline-block;
        border: 1px solid #000 !important;
        border-radius: 8px;
        padding: 6px 14px;
    }

    .bm-hdr .bm-logo {
        max-height: 58px;
        max-width: 210px;
        width: auto;
        display: block;
    }

    /* ---------- title ---------- */
    .bm-titlewrap {
        text-align: center;
        margin: 12px 0 6px;
    }

    .bm-title {
        display: inline-block;
        background: #e6e6e6 !important;
        border: 1px solid #000 !important;
        padding: 5px 46px;
        font-size: 13px;
        font-weight: 700;
    }

    /* ---------- side-by-side columns (flex, not nested tables) ---------- */
    .bm-split {
        display: flex;
        align-items: flex-start;
        gap: 14px;
    }

    .bm-split .bm-col {
        flex: 1 1 0;
        min-width: 0;
        width: 50%;
    }

    /* ---------- info + party grids ---------- */
    .bm-grid {
        page-break-inside: avoid;
    }

    .bm-grid td {
        border: 1px solid #000 !important;
        padding: 2px 5px;
        height: 20px;
        line-height: 1.2;
        font-size: 10.5px;
    }

    .bm-grid .bm-hd {
        background: #e6e6e6 !important;
        font-weight: 700;
        text-align: center;
        font-size: 12px;
        padding: 4px;
    }

    .bm-grid .bm-le {
        width: 20%;
        white-space: nowrap;
        text-align: center;
    }

    .bm-grid .bm-la {
        width: 18%;
        white-space: nowrap;
        text-align: center;
        direction: rtl;
    }

    .bm-grid .bm-vl {
        text-align: center;
    }

    /* name row: only the long legal-name VALUE shrinks to stay on one line;
       the labels keep the normal size (like the reference) */
    .bm-grid tr.bm-name-row .bm-vl {
        white-space: nowrap;
        font-size: 8.5px;
        letter-spacing: -0.2px;
    }

    .bm-party {
        margin-top: 9px;
    }

    /* ---------- items table ---------- */
    /* border-collapse: SEPARATE — Chrome's print engine drops the thead's top border
       only in COLLAPSE mode; in separate mode every edge (incl. the top) prints. Each
       cell draws its top+left edge; the table draws its right+bottom edge. spacing 0
       keeps it looking like a normal grid. */
    .bm-items-wrap {
        margin-top: 9px;
    }

    .bm-items {
        margin: 0 !important;
        border-collapse: separate !important;
        border-spacing: 0 !important;
        border: 0 !important;
        page-break-inside: auto;
    }

    .bm-items th,
    .bm-items td {
        border-top: 1px solid #000 !important;
        border-left: 1px solid #000 !important;
        padding: 3px 3px;
        font-size: 10.5px;
    }

    /* last column / last row draw their own outer edge — the table's own border can be
       painted over by the grey header cells in the app's print context */
    .bm-items tr > th:last-child,
    .bm-items tr > td:last-child {
        border-right: 1px solid #000 !important;
    }

    .bm-items tfoot tr > td,
    .bm-items tbody tr:last-child > td {
        border-bottom: 1px solid #000 !important;
    }

    .bm-items thead {
        display: table-header-group;
    }

    .bm-items thead th {
        background: #f2f2f2 !important;
        text-align: center;
        font-weight: 700;
        line-height: 1.25;
        font-size: 10.5px;
    }

    .bm-items .bm-th-ar-row th {
        direction: rtl;
        vertical-align: bottom;
        padding: 6px 3px 3px;
    }

    .bm-items .bm-th-en-row th {
        vertical-align: top;
        border-top: 1px solid #000 !important;
        padding: 3px 3px 6px;
    }

    .bm-items tbody td {
        vertical-align: middle;
        height: 26px;
    }

    .bm-items tbody tr {
        page-break-inside: avoid;
    }

    .bm-items .bm-pname {
        direction: rtl;
        text-align: right;
        white-space: normal;
        word-break: break-word;
        overflow-wrap: anywhere;
        overflow: hidden;
    }

    .bm-items .bm-code {
        white-space: nowrap;
        overflow: hidden;
        font-size: 9.5px;
    }

    .bm-items tfoot td {
        font-weight: 700;
        background: #f7f7f7 !important;
    }

    /* ---------- empty stamp band — reserved blank space for the physical company seal.
       grows to absorb the leftover page height so nothing floats near the bottom ---------- */
    .bm-stamp {
        flex: 1 1 auto;
        min-height: 110px;
    }

    /* ---------- bottom: qr | totals ---------- */
    .bm-bottom {
        margin-top: 2px;
        page-break-inside: avoid;
    }

    .bm-bottom>tbody>tr>td {
        vertical-align: bottom;
        padding: 0;
        border: 0;
    }

    .bm-qrcell {
        text-align: left;
        line-height: 0;
        font-size: 0;
    }

    .bm-qr-frame {
        width: auto !important;
        border-collapse: collapse;
    }

    .bm-qr-frame td {
        border: 1.5px solid #000 !important;
        padding: 6px;
        text-align: center;
        background: #fff !important;
    }

    .bm-qr-frame .bm-qr-img {
        display: block;
        width: 116px;
        height: 116px;
        border: 0 !important;
        max-width: none !important;
    }

    .bm-qr-frame .bm-barcode {
        display: block;
        width: 116px;
        height: 30px;
        margin-top: 5px;
        border: 0 !important;
        max-width: none !important;
    }

    .bm-totals td {
        border: 1px solid #000 !important;
        padding: 6px 10px;
        font-size: 11px;
    }

    .bm-totals .bm-le {
        width: 42%;
        font-weight: 600;
        white-space: nowrap;
    }

    .bm-totals .bm-la {
        width: 34%;
        text-align: right;
        direction: rtl;
        white-space: nowrap;
    }

    .bm-totals .bm-vl {
        text-align: left;
    }

    /* ---------- footer — its CELLS draw the sheet's bottom edge line.
       A border on the <table> itself gets dropped by the print engine in collapse mode;
       cell borders always print. ---------- */
    .bm-foot {
        margin-top: 16px;
        border: 0 !important;
        page-break-inside: avoid;
    }

    .bm-foot td {
        font-size: 10px;
        padding: 2px 3px 0;
    }
</style>

<table class="bm-sheet">
    <tr>
        <td>
            <div class="bm-wrap">

    {{-- ================= HEADER =================
         A letter head (Invoice Layout > Letter Head + "Show letter head") replaces the
         text/logo header entirely, the same way classic/elegant behave. --}}
    @if (!empty($receipt_details->letter_head))
        <table class="bm-letterhead">
            <tr>
                <td><img src="{{ $receipt_details->letter_head }}" alt="letter head"></td>
            </tr>
        </table>
    @else
    <table class="bm-hdr">
        <tr>
            <td style="width:37%;">
                <div class="bm-name">{{ $seller['name'] ?? ($receipt_details->display_name ?? '') }}</div>
                @if (!empty($receipt_details->sub_heading_line1))
                    <div class="bm-sub">{{ $receipt_details->sub_heading_line1 }}</div>
                @endif
            </td>
            <td style="width:26%;" class="bm-c">
                @if (!empty($receipt_details->logo))
                    <span class="bm-logobox"><img class="bm-logo" src="{{ $receipt_details->logo }}"
                            alt="logo"></span>
                @endif
            </td>
            <td style="width:37%;" class="bm-r bm-ar">
                @if (!empty($receipt_details->header_text))
                    <div class="bm-name">{!! strip_tags($receipt_details->header_text) !!}</div>
                @endif
                @if (!empty($receipt_details->sub_heading_line2))
                    <div class="bm-sub">{{ $receipt_details->sub_heading_line2 }}</div>
                @endif
            </td>
        </tr>
    </table>
    @endif

    {{-- ================= TITLE ================= --}}
    <div class="bm-titlewrap">
        <span class="bm-title" dir="ltr">{{ $receipt_details->invoice_heading ?? '' ?: 'Tax Invoice' }} # &nbsp;
            <span class="bm-ar">فاتورة ضريبية</span></span>
    </div>

    {{-- ================= INFO (branch / date) ================= --}}
    <div class="bm-split">
        <div class="bm-col">
            <table class="bm-grid">
                <tr>
                    <td class="bm-le">Branch</td>
                    <td class="bm-vl">{{ $seller['location_name'] ?? '' }}</td>
                    <td class="bm-la bm-ar">الفرع</td>
                </tr>
                <tr>
                    <td class="bm-le">&nbsp;</td>
                    <td class="bm-vl">&nbsp;</td>
                    <td class="bm-la bm-ar">البيان</td>
                </tr>
                <tr>
                    <td class="bm-le">Client Balance</td>
                    <td class="bm-vl">{{ $receipt_details->buyer_balance ?? '' }}@if ($sym !== '')
                            {{ ' ' . $sym }}
                        @endif
                    </td>
                    <td class="bm-la bm-ar">رصيد العميل</td>
                </tr>
            </table>
        </div>
        <div class="bm-col">
            <table class="bm-grid">
                <tr>
                    <td class="bm-le">Date</td>
                    <td class="bm-vl">{{ $receipt_details->invoice_date ?? '' }}</td>
                    <td class="bm-la bm-ar">التاريخ</td>
                </tr>
                <tr>
                    <td class="bm-le">Invoice No</td>
                    <td class="bm-vl">
                        @if (!empty($receipt_details->invoice_no_prefix))
                            {!! $receipt_details->invoice_no_prefix !!}
                        @endif{{ $receipt_details->invoice_no }}
                    </td>
                    <td class="bm-la bm-ar">رقم الفاتورة</td>
                </tr>
                <tr>
                    <td class="bm-le">Payment</td>
                    <td class="bm-vl">{{ $receipt_details->payment_method ?? '' }}</td>
                    <td class="bm-la bm-ar">طريقة الدفع</td>
                </tr>
            </table>
        </div>
    </div>

    {{-- ================= BUYER / SELLER ================= --}}
    <div class="bm-split bm-party">
        <div class="bm-col">
            <table class="bm-grid">
                <tr>
                    <td class="bm-hd" colspan="3"><span dir="ltr">Buyer - <span
                                class="bm-ar">المشتري</span></span></td>
                </tr>
                @foreach ($bm_party_rows($buyer, false) as $i => $row)
                    <tr @if ($i === 0) class="bm-name-row" @endif>
                        <td class="bm-le">{{ $row[0] }}</td>
                        <td class="bm-vl">{{ $row[1] }}</td>
                        <td class="bm-la bm-ar">{{ $row[2] }}</td>
                    </tr>
                @endforeach
            </table>
        </div>
        <div class="bm-col">
            <table class="bm-grid">
                <tr>
                    <td class="bm-hd" colspan="3"><span dir="ltr">Seller - <span
                                class="bm-ar">البائع</span></span></td>
                </tr>
                @foreach ($bm_party_rows($seller, true) as $i => $row)
                    <tr @if ($i === 0) class="bm-name-row" @endif>
                        <td class="bm-le">{{ $row[0] }}</td>
                        <td class="bm-vl">{{ $row[1] }}</td>
                        <td class="bm-la bm-ar">{{ $row[2] }}</td>
                    </tr>
                @endforeach
            </table>
        </div>
    </div>

    {{-- ================= LINE ITEMS ================= --}}
    <div class="bm-items-wrap">
        <table class="bm-items">
            <thead>
                <tr class="bm-th-ar-row">
                    <th style="width:4%;">رقم</th>
                    <th style="width:15%;">رقم الصنف</th>
                    <th style="width:27%;">اسم الصنف</th>
                    <th style="width:5%;">الكمية</th>
                    <th style="width:8%;">السعر</th>
                    <th style="width:9%;">المبلغ الخاضع للضريبة</th>
                    <th style="width:7%;">خصومات</th>
                    <th style="width:6%;">الضريبة المضافة</th>
                    <th style="width:8%;">مبلغ الضريبة</th>
                    <th style="width:11%;">الإجمالي شامل الضريبة</th>
                </tr>
                <tr class="bm-th-en-row">
                    <th>S.N</th>
                    <th class="bm-code">Product Code</th>
                    <th>Product Name</th>
                    <th>QTY</th>
                    <th>Price</th>
                    <th>Taxable Amount</th>
                    <th>Discount</th>
                    <th>Tax Rate</th>
                    <th>Tax Amount</th>
                    <th>Total With Tax</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($receipt_details->lines as $idx => $line)
                    @php
                        $q = $line['quantity_uf'] ?? 0;
                        $taxable = $line['line_total_exc_tax_uf'] ?? 0;
                        $unit_price = $line['unit_price_before_discount_uf'] ?? ($line['unit_price_uf'] ?? 0);
                        $line_disc = max($unit_price * $q - $taxable, 0);
                        $line_tax = ($line['tax_unformatted'] ?? 0) * $q;
                        $rate = $line['tax_percent'];
                    @endphp
                    <tr>
                        <td class="bm-c">{{ $idx + 1 }}</td>
                        <td class="bm-code">{{ $line['sub_sku'] ?? ($line['cat_code'] ?? '') }}</td>
                        <td class="bm-pname">
                            {{ $line['name'] }} {{ $line['product_variation'] }} {{ $line['variation'] }}
                            @if (!empty($line['product_custom_fields']))
                                <br><small>{{ $line['product_custom_fields'] }}</small>
                            @endif
                            @if (!empty($line['sell_line_note']))
                                <br><small>{!! $line['sell_line_note'] !!}</small>
                            @endif
                        </td>
                        <td class="bm-c">{{ ($line['quantity_uf'] ?? 0) + 0 }}</td>
                        <td class="bm-r">{{ $nf($unit_price) }}</td>
                        <td class="bm-r">{{ $nf($taxable) }}</td>
                        <td class="bm-r">{{ $nf($line_disc) }}</td>
                        <td class="bm-c">
                            @if ($rate !== null && $rate !== '')
                                %{{ $rate + 0 }}
                            @endif
                        </td>
                        <td class="bm-r">{{ $nf($line_tax) }}</td>
                        <td class="bm-r">{{ $nf($taxable + $line_tax) }}</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="3"></td>
                    <td class="bm-c">{{ $bm_qty + 0 }}</td>
                    <td></td>
                    <td class="bm-r">{{ $nf($bm_taxable) }}</td>
                    <td class="bm-r">{{ $nf($bm_disc) }}</td>
                    <td></td>
                    <td class="bm-r">{{ $nf($bm_tax) }}</td>
                    <td class="bm-r">{{ $nf($bm_taxable + $bm_tax) }}</td>
                </tr>
            </tfoot>
        </table>
    </div>

    {{-- ================= EMPTY STAMP BAND (reserved for physical seal) ================= --}}
    <div class="bm-stamp">&nbsp;</div>

    {{-- ================= QR  |  TOTALS ================= --}}
    <table class="bm-bottom">
        <tr>
            <td style="width:1%; white-space:nowrap;" class="bm-qrcell">
                @if (
                    (!empty($receipt_details->show_qr_code) && !empty($receipt_details->qr_code_text)) ||
                        !empty($receipt_details->show_barcode)
                )
                    {{-- framed as a 1-cell table: table borders print reliably where span border/outline may be stripped --}}
                    <table class="bm-qr-frame">
                        <tr>
                            <td>
                                @if (!empty($receipt_details->show_qr_code) && !empty($receipt_details->qr_code_text))
                                    <img class="bm-qr-img"
                                        src="data:image/png;base64,{{ DNS2D::getBarcodePNG($receipt_details->qr_code_text, 'QRCODE', 4, 4, [0, 0, 0]) }}"
                                        alt="qr">
                                @endif
                                @if (!empty($receipt_details->show_barcode))
                                    <img class="bm-barcode"
                                        src="data:image/png;base64,{{ DNS1D::getBarcodePNG($receipt_details->invoice_no, 'C128', 2, 28, [0, 0, 0], true) }}"
                                        alt="barcode">
                                @endif
                            </td>
                        </tr>
                    </table>
                @endif
            </td>
            <td style="width:16px;"></td>
            <td>
                <table class="bm-totals">
                    <tr>
                        <td class="bm-le">Total</td>
                        <td class="bm-vl">{{ $nf($bm_taxable + $bm_disc) }}</td>
                        <td class="bm-la bm-ar">الإجمالي</td>
                    </tr>
                    <tr>
                        <td class="bm-le">Total Discount</td>
                        <td class="bm-vl">{{ $nf($bm_disc) }}</td>
                        <td class="bm-la bm-ar">إجمالي الخصومات</td>
                    </tr>
                    <tr>
                        <td class="bm-le">Total Taxable Amount</td>
                        <td class="bm-vl">{{ $nf($bm_taxable) }}</td>
                        <td class="bm-la bm-ar">الإجمالي الخاضع للضريبة</td>
                    </tr>
                    <tr>
                        <td class="bm-le">Total Tax</td>
                        <td class="bm-vl">{{ $nf($bm_tax) }}</td>
                        <td class="bm-la bm-ar">إجمالي الضريبة</td>
                    </tr>
                    <tr>
                        <td class="bm-le">Total With Tax</td>
                        <td class="bm-vl">{{ $nf($bm_grand) }}</td>
                        <td class="bm-la bm-ar">الإجمالي شامل الضريبة</td>
                    </tr>
                    @if (!empty($receipt_details->total_in_words))
                        <tr>
                            <td colspan="3"><small>{{ $receipt_details->total_in_words }}</small></td>
                        </tr>
                    @endif
                </table>
            </td>
        </tr>
    </table>

    {{-- ================= FOOTER ================= --}}
    <table class="bm-foot">
        <tr>
            <td style="width:40%;">{{ \Carbon\Carbon::now()->format('Y/m/d H:i:s') }} &nbsp; <span
                    class="bm-ar">تاريخ الطباعة</span></td>
            <td style="width:26%;" class="bm-c"><span class="bm-ar">صفحة رقم</span> ( 1 / 1 )</td>
            <td style="width:34%;" class="bm-r">{{ $printed_by }} : <span class="bm-ar">طبعت بواسطة</span></td>
        </tr>
    </table>

            </div>
        </td>
    </tr>
</table>
