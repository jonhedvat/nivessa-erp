@extends('layouts.app')
@section('title', __('product.mass_add_new_products'))

@section('content')
    {{-- Sarah 2026-05-06: visual reskin to match /pos/create. Pure CSS,
         scoped under body.mass-add-v2 — leaves all IDs / handlers alone. --}}
    @include('product.partials._mass_create_redesign')
    <script>document.body.classList.add('mass-add-v2');</script>

    <!-- Content Header (Page header) -->
    <section class="content-header">
        <h1>@lang('product.mass_add_new_products')</h1>
        <!-- <ol class="breadcrumb">
            <li><a href="#"><i class="fa fa-dashboard"></i> Level</a></li>
            <li class="active">Here</li>
        </ol> -->
    </section>



    <style>
        /* Внешний контейнер с горизонтальной прокруткой */
        .responsive-table {
            width: 100%;
            max-width: 100%;
            overflow-x: auto;
            overflow-y: hidden;
            border: 1px solid #ddd;
            margin: 20px 0;
        }

        #mass_create_table {
            white-space: nowrap;
            table-layout: fixed;
            width: 1350px;
            min-width: 1350px;
            max-width: 1350px;
        }

        #mass_create_table .thead .th,
        #mass_create_table .tbody .td {
            white-space: nowrap;
            min-width: 140px;
        }

        /* Product name column */
        #mass_create_table .col-name {
            min-width: 200px;
            width: 200px;
        }
        /* SKU - narrow */
        #mass_create_table .col-sku {
            min-width: 100px;
            width: 100px;
        }
        /* Category / Sub Category - need room for select2 + copy-down */
        #mass_create_table .col-select {
            min-width: 200px;
            width: 200px;
        }
        /* Artist (narrower) */
        #mass_create_table .col-artist {
            min-width: 90px;
            width: 90px;
        }

        /* Mass Add artist: floating panel is fixed to viewport (see #mass-add-artist-floating-panel) */
        .mass-add-artist-wrap {
            position: relative;
        }
        #mass-add-artist-floating-panel {
            display: none;
            position: fixed;
            z-index: 10050;
            max-height: 260px;
            overflow-y: auto;
            overflow-x: hidden;
            background: #fff;
            border: 1px solid #ccc;
            border-radius: 2px;
            box-shadow: 0 4px 14px rgba(0, 0, 0, 0.2);
            text-align: left;
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        .mass-add-artist-option {
            display: block;
            width: 100%;
            padding: 8px 10px;
            margin: 0;
            border: 0;
            border-bottom: 1px solid #eee;
            background: #fff;
            color: #333;
            font-size: 13px;
            line-height: 1.35;
            text-align: left;
            cursor: pointer;
        }
        .mass-add-artist-option:last-child {
            border-bottom: 0;
        }
        .mass-add-artist-option:hover,
        .mass-add-artist-option:focus {
            background: #e8f4fc;
            outline: none;
        }
        .mass-add-artist-option:active {
            background: #d0e8f7;
        }
        /* Business Locations (narrower) */
        #mass_create_table .col-locations {
            min-width: 130px;
            width: 130px;
        }
        /* Action column - narrow */
        #mass_create_table .col-action {
            min-width: 50px !important;
            width: 50px;
        }

        .table-wrapper {
            display: table;
            width: 1350px;
            min-width: 1350px;
            max-width: 1350px;
            border-collapse: collapse;
        }

        /* Keep selects/inputs from stretching row width as new rows are added */
        #mass_create_table .td .form-control,
        #mass_create_table .td .select2-container {
            width: 100% !important;
            max-width: 100% !important;
            min-width: 0 !important;
            box-sizing: border-box;
        }

        /* Заголовок таблицы */
        .thead {
            display: table-header-group;
            background: #f5f5f5;
            font-weight: bold;
        }

        /* Тело таблицы */
        .tbody {
            display: table-row-group;
        }

        /* Подвал таблицы */
        .tfoot {
            display: table-footer-group;
            background: transparent;
        }

        /* Mass Add footer action buttons background should match page */
        #mass_add_action_buttons {
            background: transparent !important;
        }

        /* Ряды таблицы */
        .tr {
            display: table-row;
            border-bottom: 1px solid #ddd;
        }

        .th, .td {
            display: table-cell;
            padding: 8px 6px;
            box-sizing: border-box;
            text-align: center;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            border-right: 1px solid #ddd;
        }

        /* Удаление правой границы у последней ячейки в ряду */
        .tr > .th:last-child,
        .tr > .td:last-child {
            border-right: none;
        }

        /* Подсветка строк тела при наведении */
        .tbody .tr:hover {
            background: #f9f9f9;
        }

        /* Стиль подвала */
        .tfoot .tr {
            display: table-row;
        }
        .tfoot .td {
            text-align: center;
            padding: 8px;
        }
        .tfoot .btn {
            width: 200px;
            margin: 8px auto;
            display: block;
        }

        .is-invalid {
            border-color: red;
        }
        .invalid-feedback {
            color: red;
            font-size: 0.9em;
        }

        /* Адаптивный режим */
        @media (max-width: 768px) {
            .table-wrapper {
                min-width: 1350px;
            }
            .th, .td {
                white-space: normal;
            }
            .tfoot .btn {
                width: 100%;
            }
        }

        .expandable {
            display: none;
        }

        #mass_create_table .price-col {
            min-width: 90px !important;
            max-width: 110px;
            width: 90px;
        }
        #mass_create_table .price-col .form-control {
            min-width: 70px;
            padding: 6px 4px;
            text-align: right;
        }

        .price-recomendation-card-wrapper {
            display: flex;
            flex-direction: row;
            gap: 10px;
        }

        /* Price Recommendation Styles - REMOVED: eBay/Discogs suggestions disabled */
        /* Subcategory Suggestions Styles */
        .sub-category-suggestion-item {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            padding: 15px;
            background: linear-gradient(145deg, #f6f8fa, #ffffff);
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            border: 1px solid #e1e4e8;
            border-radius: 8px;
            margin: 10px 0;
        }

        .sub-category-suggestion-item h4 {
            color: #24292e;
            margin: 0 0 12px 0;
            font-size: 14px;
            font-weight: 600;
            border-bottom: 1px solid #e1e4e8;
            padding-bottom: 8px;
            width: 100%;
        }

        .sub-category-suggestion-item-name {
            display: inline-block;
            padding: 4px 12px;
            background: #e9ecef;
            border-radius: 16px;
            font-size: 13px;
            color: #495057;
            cursor: pointer;
            transition: all 0.2s ease;
            border: 1px solid #dee2e6;
        }

        .sub-category-suggestion-item-name:hover {
            background: #dee2e6;
            color: #212529;
            transform: translateY(-1px);
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        
        /* Bulk text entry enhancements */
        #bulk_preview_container {
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 15px;
            background: #f9f9f9;
        }
        
        #bulk_preview_table {
            background: white;
            border-radius: 4px;
        }
        
        #bulk_preview_table table {
            font-size: 12px;
        }
        
        #bulk_preview_table th {
            background: #f5f5f5;
            font-weight: 600;
            padding: 8px;
        }
        
        #bulk_preview_table td {
            padding: 6px 8px;
        }
        
        #bulk_product_text {
            border: 2px solid #ddd;
            transition: border-color 0.3s;
        }
        
        #bulk_product_text:focus {
            border-color: #3c8dbc;
            box-shadow: 0 0 5px rgba(60, 141, 188, 0.3);
        }
        
        kbd {
            background: #f4f4f4;
            border: 1px solid #ccc;
            border-radius: 3px;
            padding: 2px 6px;
            font-size: 11px;
            font-family: monospace;
            box-shadow: 0 1px 0 rgba(0,0,0,0.2);
        }
    </style>

    {!! Form::open(['url' => action('ProductController@massStore'), 'method' => 'post', 'id' => 'mass_create_form', 'enctype' => 'multipart/form-data' ]) !!}

    {{-- Optional: link these products to the buy-from-customer record they came
         from, so Buy Results can follow them to their sales. Blank = normal
         Mass Add (old collections with no buy number). --}}
    <div class="box box-solid" id="bfc_buy_box" style="margin-bottom: 20px;">
        <div class="box-body">
            <div class="form-inline">
                <label for="bfc_buy_number" style="margin-right:6px;"><i class="fa fa-archive"></i> Buy record # <small class="text-muted">(optional)</small></label>
                <input type="text" id="bfc_buy_number" class="form-control input-sm" placeholder="e.g. BFC-000123" style="width:160px;">
                <button type="button" id="bfc_buy_load" class="btn btn-default btn-sm">Load buy</button>
                <button type="button" id="bfc_buy_clear" class="btn btn-link btn-sm" style="display:none;">Clear</button>
                <input type="hidden" name="buy_offer_id" id="bfc_buy_offer_id" value="">
            </div>
            <div id="bfc_buy_summary" style="display:none; margin-top:8px;"></div>
        </div>
    </div>

    <!-- Default Cost Prices reference (collapsed by default) -->
    <div class="box box-info collapsed-box" style="margin-bottom: 20px;">
        <div class="box-header with-border" style="cursor: pointer;" data-widget="collapse">
            <h3 class="box-title">
                <i class="fa fa-tags"></i> Default Cost Prices by Category
            </h3>
            <div class="box-tools pull-right">
                <button type="button" class="btn btn-sm btn-info" data-widget="collapse">
                    <i class="fa fa-plus"></i> Show Pricing Rules
                </button>
            </div>
        </div>
        <div class="box-body" style="display: none;">
            <div class="alert alert-info" style="margin-bottom: 10px;">
                If you leave the purchase price blank (or set it to 0), the
                <a href="{{ url('admin/cost-price-rules') }}" target="_blank">cost-price-rules tool</a>
                can later fill it with the category default below. Existing non-zero costs are never overwritten.
            </div>
            <div class="row">
                <div class="col-md-6">
                    <table class="table table-condensed table-bordered">
                        <thead>
                            <tr><th>Category</th><th class="text-right">Default cost</th></tr>
                        </thead>
                        <tbody>
                            <tr><td>Sealed Vinyl</td><td class="text-right">$17.00</td></tr>
                            <tr><td>Used Vinyl</td><td class="text-right">$0.35</td></tr>
                            <tr><td>Sealed CD / CD (Sealed)</td><td class="text-right">$6.00</td></tr>
                            <tr><td>Used CD</td><td class="text-right">$0.10</td></tr>
                            <tr><td>Cassettes &mdash; Sealed</td><td class="text-right">$6.00</td></tr>
                            <tr><td>Cassettes (used)</td><td class="text-right">$0.30</td></tr>
                            <tr><td>VHS</td><td class="text-right">$0.10</td></tr>
                            <tr><td>7", 45 RPM</td><td class="text-right">$0.15</td></tr>
                            <tr><td>8 track</td><td class="text-right">$0.25</td></tr>
                            <tr><td>DVD</td><td class="text-right">$0.25</td></tr>
                            <tr><td>Blu-ray</td><td class="text-right">$0.25</td></tr>
                            <tr><td>Movies</td><td class="text-right">$0.25</td></tr>
                            <tr><td>Laser Disc</td><td class="text-right">$0.20</td></tr>
                        </tbody>
                    </table>
                </div>
                <div class="col-md-6">
                    <table class="table table-condensed table-bordered">
                        <thead>
                            <tr><th>Category</th><th class="text-right">Default cost</th></tr>
                        </thead>
                        <tbody>
                            <tr><td>Books &amp; Magazines</td><td class="text-right">$0.40</td></tr>
                            <tr><td>Magazines</td><td class="text-right">$1.50</td></tr>
                            <tr><td>Trading Cards</td><td class="text-right">$6.00</td></tr>
                            <tr><td>Apparel</td><td class="text-right">$3.00</td></tr>
                            <tr><td>Clothing</td><td class="text-right">$3.00</td></tr>
                            <tr><td>Video Games</td><td class="text-right">$1.25</td></tr>
                            <tr><td>Record Players</td><td class="text-right">$35.00</td></tr>
                            <tr><td>Audio Gear</td><td class="text-right">$20.00</td></tr>
                            <tr><td>Gift Items</td><td class="text-right">$4.00</td></tr>
                            <tr><td>Toys</td><td class="text-right">$3.00</td></tr>
                            <tr><td>Accessories &amp; Novelties</td><td class="text-right">$2.00</td></tr>
                            <tr><td>Pictures &amp; Posters</td><td class="text-right">$5.00</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <small class="text-muted">
                <i class="fa fa-info-circle"></i>
                Rules are applied only to variations whose default purchase price is NULL or 0. Manage them at
                <a href="{{ url('admin/cost-price-rules') }}" target="_blank">/admin/cost-price-rules</a>.
            </small>
        </div>
    </div>

    <!-- Bulk Text Entry Section (collapsed by default) -->
    <div class="box box-primary collapsed-box" style="margin-bottom: 20px;">
        <div class="box-header with-border" style="cursor: pointer;" data-widget="collapse">
            <h3 class="box-title">
                <i class="fa fa-file-text"></i> Bulk Product Entry
                {{-- Sarah 2026-07-22: source-platform logos next to the heading (eBay / ChatGPT / Claude). --}}
                <span style="display:inline-flex; align-items:center; gap:8px; margin-left:8px; vertical-align:middle;">
                    {{-- eBay wordmark --}}
                    <span title="eBay" style="font-family:Arial,Helvetica,sans-serif; font-style:italic; font-weight:bold; font-size:16px; letter-spacing:-0.5px; line-height:1;"><span style="color:#E53238;">e</span><span style="color:#0064D2;">b</span><span style="color:#F5AF02;">a</span><span style="color:#86B817;">y</span></span>
                    {{-- ChatGPT / OpenAI logomark --}}
                    <svg title="ChatGPT" role="img" aria-label="ChatGPT" viewBox="0 0 24 24" style="height:17px; width:17px; vertical-align:middle;" fill="#10A37F" xmlns="http://www.w3.org/2000/svg"><path d="M22.2819 9.8211a5.9847 5.9847 0 0 0-.5157-4.9108 6.0462 6.0462 0 0 0-6.5098-2.9A6.0651 6.0651 0 0 0 4.9807 4.1818a5.9847 5.9847 0 0 0-3.9977 2.9 6.0462 6.0462 0 0 0 .7427 7.0966 5.98 5.98 0 0 0 .511 4.9107 6.051 6.051 0 0 0 6.5146 2.9001A5.9847 5.9847 0 0 0 13.2599 24a6.0557 6.0557 0 0 0 5.7718-4.2058 5.9894 5.9894 0 0 0 3.9977-2.9001 6.0557 6.0557 0 0 0-.7469-7.0729zm-9.022 12.6081a4.4755 4.4755 0 0 1-2.8764-1.0408l.1419-.0804 4.7783-2.7582a.7948.7948 0 0 0 .3927-.6813v-6.7369l2.02 1.1686a.071.071 0 0 1 .038.052v5.5826a4.504 4.504 0 0 1-4.4945 4.4944zm-9.6607-4.1254a4.4708 4.4708 0 0 1-.5346-3.0137l.1419.0852 4.783 2.7582a.7712.7712 0 0 0 .7806 0l5.8428-3.3685v2.3324a.0804.0804 0 0 1-.0332.0615L9.74 19.9502a4.4992 4.4992 0 0 1-6.1408-1.6464zM2.3408 7.8956a4.485 4.485 0 0 1 2.3655-1.9728V11.6a.7664.7664 0 0 0 .3879.6765l5.8144 3.3543-2.0201 1.1685a.0757.0757 0 0 1-.071 0l-4.8303-2.7865A4.504 4.504 0 0 1 2.3408 7.872zm16.5963 3.8558L13.1038 8.364 15.1192 7.2a.0757.0757 0 0 1 .071 0l4.8303 2.7913a4.4944 4.4944 0 0 1-.6765 8.1042v-5.6772a.79.79 0 0 0-.407-.667zm2.0107-3.0231l-.142-.0852-4.7735-2.7818a.7759.7759 0 0 0-.7854 0L9.409 9.2297V6.8974a.0662.0662 0 0 1 .0284-.0615l4.8303-2.7866a4.4992 4.4992 0 0 1 6.6802 4.66zM8.3065 12.863l-2.02-1.1638a.0804.0804 0 0 1-.038-.0567V6.0742a4.4992 4.4992 0 0 1 7.3757-3.4537l-.142.0805L8.704 5.459a.7948.7948 0 0 0-.3927.6813zm1.0976-2.3654l2.602-1.4998 2.6069 1.4998v2.9994l-2.5974 1.4997-2.6067-1.4997Z"/></svg>
                    {{-- Claude / Anthropic logomark --}}
                    <svg title="Claude" role="img" aria-label="Claude" viewBox="0 0 46 32" style="height:16px; width:auto; vertical-align:middle;" fill="#D97757" xmlns="http://www.w3.org/2000/svg"><path d="M32.73 0h-6.94l12.65 32h6.94L32.73 0ZM13.27 0 .62 32h7.08l2.59-6.71h13.24l2.59 6.71h7.08L20.55 0h-7.28Zm-.65 19.34 4.34-11.24 4.34 11.24h-8.68Z"/></svg>
                </span>
            </h3>
            <div class="box-tools pull-right">
                <button type="button" class="btn btn-sm btn-primary" data-widget="collapse">
                    <i class="fa fa-plus"></i> Open Bulk Entry
                </button>
            </div>
        </div>
        <div class="box-body" style="display: none;">
            {{-- Sarah 2026-11-15: chat-style entry. Staff type a rough list and the
                 server asks Claude to price + format it (MassAddAiPriceController),
                 instead of copy-pasting a prompt into ChatGPT. --}}
            <div style="padding:16px; background:#f7f7f8; border:1px solid #e3e3e8; border-radius:12px; max-width:900px;">
                <div style="font-size:18px; font-weight:800; color:#111; margin-bottom:4px;">
                    <i class="fa fa-magic"></i> Type what you have &mdash; we&rsquo;ll price it
                </div>
                <div style="font-size:14px; color:#555; margin-bottom:10px;">
                    One item per line, like you would type to ChatGPT. You can start with a note like
                    <em>&ldquo;here are a few DVDs I want to price&rdquo;</em>. AI fills in the artist, category and price,
                    checks what we&rsquo;ve sold it for and how fast it sells so it never goes under that, then adds everything to the table below.
                </div>
                <textarea
                    id="bulk_product_text"
                    class="form-control"
                    rows="8"
                    placeholder="Rumours LP VG+&#10;Thriller sealed&#10;Kind of Blue CD&#10;Nevermind cassette"
                    style="font-size:16px; line-height:1.6; border-radius:10px; padding:12px 14px; background:#fff;"></textarea>
                <div style="margin-top:10px; display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
                    <button type="button" class="btn btn-primary btn-lg" id="ai_price_bulk_text" style="border-radius:8px;">
                        <i class="fa fa-magic"></i> Price it &amp; add to table
                    </button>
                    <span class="text-muted" id="bulk_parse_status"></span>
                </div>
                <div id="ai_price_history" style="display:none; margin-top:10px; padding:10px 12px; background:#fff; border:1px solid #e3e3e8; border-radius:8px; font-size:13px;"></div>
                <div style="font-size:12px; color:#777; margin-top:8px;">
                    If our store usually sells an item for more (3+ sales, most higher), its price is raised and shows in
                    <span style="color:#15803d;font-weight:700;">green</span>. Fill anything left blank in the table, then hit <strong>Save All Products</strong>.
                </div>

                <details style="margin-top:14px;">
                    <summary style="cursor:pointer; color:#555; font-size:13px;">Already have a priced list? (CSV, pipe or tab format)</summary>
                    <div style="margin-top:8px; font-size:13px;">
                        Paste it in the box above and use these buttons instead. Formats:
                        <ul style="margin:4px 0 8px; padding-left:20px;">
                            <li><code>Product Name - Artist</code></li>
                            <li><code>Product Name,Artist,Category,Subcategory,SKU,Price,Bin,Location</code> (Bin/Location optional)</li>
                            <li><code>Product Name | Artist | Category | Subcategory | SKU | Price | Bin | Location | Media grade</code></li>
                            <li>Tab-separated, same order as CSV</li>
                        </ul>
                        <button type="button" class="btn btn-success btn-sm" id="parse_bulk_text">
                            <i class="fa fa-plus"></i> Parse &amp; Add Products
                        </button>
                        <button type="button" class="btn btn-info btn-sm" id="preview_bulk_text">
                            <i class="fa fa-eye"></i> Preview
                        </button>
                        <button type="button" class="btn btn-default btn-sm" id="clear_bulk_text">
                            <i class="fa fa-eraser"></i> Clear
                        </button>
                        <button type="button" class="btn btn-warning btn-sm" id="format_bulk_text">
                            <i class="fa fa-align-left"></i> Auto-Format
                        </button>
                    </div>
                </details>
            </div>
            <div id="bulk_preview_container" style="display: none; margin-top: 15px;">
                <div class="alert alert-warning">
                    <strong>Preview:</strong> <span id="bulk_preview_count">0</span> products detected
                </div>
                <div id="bulk_preview_table" style="max-height: 300px; overflow-y: auto; border: 1px solid #ddd; padding: 10px; background: #f9f9f9;">
                    <!-- Preview will be inserted here -->
                </div>
            </div>
        </div>
    </div>

    {{-- Sarah 2026-05-06: Bulk Discogs Release IDs entry. Paste a list of
         Discogs release IDs, the frontend fetches each one via the new
         /product/mass-create/fetch-discogs-release endpoint and prepends
         a row pre-filled with name / artist / category from Discogs. --}}
    <div class="box box-primary collapsed-box" style="margin-bottom: 20px;">
        <div class="box-header with-border" style="cursor: pointer;" data-widget="collapse">
            <h3 class="box-title">
                <i class="fa fa-music"></i> Bulk Discogs Release IDs
                <span style="font-size: 14px; font-weight: 600; color: #000; margin-left: 8px;">Type: number, price, media grade, sleeve grade (e.g. 1873085 19.99 VG+ G+)</span>
                {{-- Sarah 2026-07-22: Discogs logo next to the heading. --}}
                <span style="display:inline-flex; align-items:center; gap:5px; margin-left:8px; vertical-align:middle;">
                    <svg title="Discogs" role="img" aria-label="Discogs" viewBox="0 0 32 32" style="height:18px; width:18px;" xmlns="http://www.w3.org/2000/svg"><g fill="none" stroke="#333333" stroke-width="2.4"><circle cx="16" cy="16" r="14"/><circle cx="16" cy="16" r="8.2"/></g><circle cx="16" cy="16" r="2.4" fill="#333333"/></svg>
                    <span style="font-family:'Avenir Next','Century Gothic',Futura,'Trebuchet MS',sans-serif; font-weight:bold; font-size:17px; letter-spacing:-0.3px; color:#111111; line-height:1;">Discogs</span>
                </span>
            </h3>
            <div class="box-tools pull-right">
                <button type="button" class="btn btn-sm btn-primary" data-widget="collapse">
                    <i class="fa fa-plus"></i> Open Discogs Bulk
                </button>
            </div>
        </div>
        <div class="box-body" style="display: none;">
            <div class="form-group">
                {{-- Plain how-to, condition included (Sarah, 2026-10-26: "more obvious suggestive text") --}}
                <div style="margin-bottom: 12px; padding: 12px 14px; background: #eff6ff; border: 1px solid #bfdbfe; border-radius: 8px; color: #000; font-size: 15px;">
                    <div style="font-weight: 800; font-size: 17px; margin-bottom: 6px;">One record per line: Discogs number, price, media grade, sleeve grade</div>
                    <div style="font-family: 'Courier New', monospace; font-size: 18px; font-weight: 700; background: #fff; border: 1px solid #bfdbfe; border-radius: 6px; padding: 8px 10px; margin-bottom: 8px;">
                        1873085 &nbsp;19.99 &nbsp;VG+ &nbsp;G+
                    </div>
                    <div style="margin-bottom: 6px;"><strong>1873085</strong> is the number at the end of the Discogs release link (you can paste the whole link instead). <strong>19.99</strong> is our price. <strong>VG+</strong> is the record, <strong>G+</strong> is the sleeve, with a space between them.</div>
                    <div style="margin-bottom: 6px;">If our store has consistently sold that release for more than the price you typed (3+ sales, most of them higher), the price is raised to what we usually get and shows in <span style="color:#15803d;font-weight:700;">green</span> with the old price underneath.</div>
                    <div style="margin-bottom: 6px;"><strong>Always add the condition.</strong> It prints on the sticker and shows on the website, so the customer knows what they are getting.</div>
                    <table style="border-collapse: collapse; font-size: 14px; margin-top: 4px;">
                        <tr><td style="padding: 2px 12px 2px 0;"><code>Sealed</code></td><td>still in shrink</td></tr>
                        <tr><td style="padding: 2px 12px 2px 0;"><code>M</code> / <code>NM</code></td><td>mint / near mint, looks unplayed</td></tr>
                        <tr><td style="padding: 2px 12px 2px 0;"><code>VG+</code></td><td>light marks, plays great</td></tr>
                        <tr><td style="padding: 2px 12px 2px 0;"><code>VG</code></td><td>marks you can see and some surface noise</td></tr>
                        <tr><td style="padding: 2px 12px 2px 0;"><code>G+</code> / <code>G</code></td><td>heavy wear, plays through with noise</td></tr>
                        <tr><td style="padding: 2px 12px 2px 0;"><code>F</code> / <code>P</code></td><td>fair / poor, damaged</td></tr>
                        <tr><td style="padding: 2px 12px 2px 0;"><code>generic</code> / <code>nocover</code></td><td>sleeve only: plain sleeve, or no cover at all</td></tr>
                    </table>
                    <div style="margin-top: 8px;">More examples: <code>1873085 19.99 VG+</code> (sleeve not graded), <code>1873085 VG 12</code> (either order works), <code>1873085 8 VG generic</code>, <code>1873085 19.99 NM VG+</code>, <code>1873085 29.99 sealed</code></div>
                </div>
                <textarea
                    id="bulk_discogs_ids"
                    class="form-control"
                    rows="8"
                    placeholder="Type one per line, like this:&#10;1873085 19.99 VG+ G+&#10;249504 25 NM VG+&#10;366070 12.50 VG generic&#10;https://www.discogs.com/release/1234567 29.99 sealed"
                    style="font-family: 'Courier New', monospace; font-size: 15px; line-height: 1.6;"></textarea>
                {{-- Live check of each line before fetching --}}
                <div id="bulk_discogs_preview" style="margin-top: 8px; font-size: 14px; color: #000;"></div>
                <details style="margin-top: 8px; color: #000; font-size: 13px;">
                    <summary style="cursor: pointer;">Adding something that is not on Discogs?</summary>
                    Type a CSV row instead: <code>Product Name,Artist,Category,Subcategory,SKU,Price</code> (leave any field blank, e.g. <code>Some LP,Some Artist,,,,19.99</code>). Wrap fields with commas in double quotes.
                </details>
            </div>
            <div class="form-group">
                <button type="button" class="btn btn-success" id="fetch_discogs_ids">
                    <i class="fa fa-cloud-download"></i> Fetch from Discogs &amp; Add
                </button>
                <button type="button" class="btn btn-default" id="clear_discogs_ids">
                    <i class="fa fa-eraser"></i> Clear
                </button>
                <span class="text-muted" id="discogs_fetch_status" style="margin-left: 15px;"></span>
            </div>
        </div>
    </div>

    {{-- Sarah 2026-05-06: Preset Category bulk entry. Pick a category +
         subcategory once, then paste product names — every row created
         gets that fixed category. Saves clicking the dropdown for each row
         when adding a batch of the same kind (e.g. 30 Used Vinyl > Pop). --}}
    <div class="box box-primary collapsed-box" style="margin-bottom: 20px;">
        <div class="box-header with-border" style="cursor: pointer;" data-widget="collapse">
            <h3 class="box-title">
                <i class="fa fa-tag"></i> Preset Category Bulk Entry
            </h3>
            <div class="box-tools pull-right">
                <button type="button" class="btn btn-sm btn-primary" data-widget="collapse">
                    <i class="fa fa-plus"></i> Open Preset Bulk
                </button>
            </div>
        </div>
        <div class="box-body" style="display: none;">
            <div class="form-group">
                <label for="preset_bulk_category">
                    <strong>1. Pick the category for every row in this batch:</strong>
                </label>
                <select id="preset_bulk_category" class="form-control select2" style="width: 100%;">
                    <option value="">Please select a category &gt; subcategory</option>
                    @if(!empty($category_combos))
                        @foreach($category_combos as $combo)
                            <option value="{{ $combo['id'] }}"
                                    data-category-id="{{ $combo['category_id'] }}"
                                    data-sub-category-id="{{ $combo['sub_category_id'] }}">
                                {{ $combo['label'] }}
                            </option>
                        @endforeach
                    @endif
                </select>
            </div>
            <div class="form-group">
                <label for="preset_bulk_text">
                    <strong>2. Paste product lines (one per line).</strong>
                    Each row gets pre-filled with <em>Product Name, Artist, SKU</em> and your preset Category — same set of columns the Bulk Discogs IDs flow fills. You finish price / location / bin inline.
                </label>
                <div class="alert alert-info" style="margin-bottom: 10px; padding: 10px;">
                    <strong>Supported per-line formats:</strong>
                    <ul style="margin-bottom: 0; padding-left: 20px;">
                        <li><code>Product Name</code> (just the title)</li>
                        <li><code>Product Name - Artist</code></li>
                        <li><code>Product Name | Artist | SKU</code> (pipe / comma / tab)</li>
                    </ul>
                </div>
                <textarea
                    id="preset_bulk_text"
                    class="form-control"
                    rows="10"
                    placeholder="Example:&#10;Thriller - Michael Jackson&#10;Greatest Hits - Dionne Warwick&#10;Rumours | Fleetwood Mac | BSK 3010"
                    style="font-family: 'Courier New', monospace; font-size: 13px; line-height: 1.6;"></textarea>
            </div>
            <div class="form-group">
                <button type="button" class="btn btn-success" id="add_preset_bulk">
                    <i class="fa fa-plus-circle"></i> Add Rows with this Category
                </button>
                <button type="button" class="btn btn-default" id="clear_preset_bulk">
                    <i class="fa fa-eraser"></i> Clear
                </button>
                <span class="text-muted" id="preset_bulk_status" style="margin-left: 15px;"></span>
            </div>
        </div>
    </div>

    <div class="responsive-table">
        <table class="table-wrapper" id="mass_create_table">
            <!-- Шапка таблицы с восстановленными колонками -->
            <thead class="thead">
                {{-- Sarah 2026-05-06 (rev 2): Artist must sit at col 2 to match the
                     row partial (mass_product_row.blade.php). Touching this comment
                     to bust any stale Blade compiled-view / OPcache holding the
                     pre-move column order. --}}
                <tr class="tr">
                    <th class="th col-name">@lang('product.product_name')*</th>
                    <th class="th col-artist">Artist</th>
                    <th class="th col-sku">
                        @lang('product.sku')
                        <a href="#" id="clear_all_skus" class="text-muted" style="font-size: 10px; font-weight: normal; margin-left: 4px;" title="Clear all SKU values">clear skus</a>
                    </th>
                    <th class="th col-select">@lang('product.category') / @lang('product.sub_category')</th>
                    <th class="th col-locations">@lang('business.business_locations')</th>
                    <th class="th price-col">Purchase Price</th>
                    <th class="th price-col">Selling Price</th>
                    <th class="th col-condition">Condition</th>
                    <th class="th" style="min-width: 60px; width: 60px;">
                        <button type="button" class="btn btn-primary btn-xs show-expandables">
                            More
                        </button>
                    </th>
                    <th class="th expandable">Bin Position</th>
                    <th class="th expandable">Listing Location</th>
                    <th class="th expandable">Product Image Url</th>
                    <th class="th expandable">Upload Product Image</th>
                    <th class="th expandable">Product Description</th>
                    <th class="th col-action">@lang('messages.action')</th>
                </tr>
            </thead>

            <!-- Тело таблицы -->
            <tbody class="tbody" id="product_rows_container">
                @include('product.partials.mass_product_row', ['index' => 0])
                <!-- Добавляйте новые .tr для каждой новой строки продукта -->
            </tbody>

        </table>
    </div>

    {{-- Sarah 2026-05-06: action buttons moved OUT of <tfoot><td colspan=1>
         (which clamped them to a single column width and squished labels).
         Now they're a sibling block under the table and free to size to the
         viewport. --}}
    <div class="mass-add-footer-actions">
        <div class="mass-add-row-actions">
            <button type="button" class="btn btn-primary" id="add_row">
                Add New Product Row
            </button>
            <button type="button" class="btn btn-info" id="add_5_rows">
                Add 5 Product Rows
            </button>
            <button type="button" class="btn btn-info" id="verify_all_categories">
                <i class="fa fa-check-circle"></i> Verify All Categories
            </button>
        </div>
        <div id="mass_add_action_buttons">
            {{-- Sarah 2026-05-06: Save & send to add purchase is the primary
                 (green); Save All Products is the secondary (yellow). --}}
            <button type="button" class="btn btn-warning" id="save_all_products">
                <i class="fa fa-check"></i> Save All Products
            </button>
            <button type="button" class="btn btn-success" id="save_and_send_to_purchase">
                <i class="fa fa-save"></i> Save &amp; send to add purchase
            </button>
        </div>
    </div>

    {!! Form::close() !!}

    {{-- Blocking overlay shown while products are being saved. Saving many rows
         can take ~20s, so this reassures staff and prevents double-submits. --}}
    <div id="mass_add_saving_overlay" style="display:none; position:fixed; inset:0; z-index:20000; background:rgba(0,0,0,0.55); align-items:center; justify-content:center;">
        <div style="background:#fff; border-radius:8px; padding:28px 36px; text-align:center; max-width:420px; box-shadow:0 4px 24px rgba(0,0,0,0.3);">
            <i class="fa fa-spinner fa-spin fa-3x" style="color:#28a745;"></i>
            <h4 style="margin-top:18px; margin-bottom:6px;">Please wait — processing…</h4>
            <p style="margin-bottom:0; color:#666;">Saving your products. This can take up to a minute for larger batches.<br><strong>Please don't click again or close this page.</strong></p>
        </div>
    </div>

    {{-- Fixed to viewport so it is not clipped by .responsive-table overflow or table cell overflow --}}
    <div id="mass-add-artist-floating-panel" class="mass-add-artist-floating-root" aria-hidden="true"></div>

    <!-- Discogs Price Suggestions Modal - REMOVED: eBay/Discogs suggestions disabled -->

@endsection


@section('javascript')
@php $asset_v = env('APP_VERSION'); @endphp
<script>
    window.manualItemPriceRules = @json($manual_item_price_rules ?? []);
    window.currentPosLocationId = @json($current_pos_location_id ?? null);
    // Default purchase price (cost) per parent category id, from Sarah's
    // cost-price-rules table. Used to pre-fill a row's purchase price from the
    // chosen category so "Save & send to add purchase" carries a cost for
    // every category (sealed vinyl 17, used vinyl 0.35, …), not just used LPs.
    window.categoryCostDefaults = @json($category_cost_defaults ?? (object) []);

    // Same cost-price-rules table, as (alias-pattern -> cost) pairs, sorted
    // longest-pattern-first so the most specific name wins (e.g. "cassettes -
    // sealed" beats "cassettes"). This is the fallback the row uses when the
    // chosen category's name doesn't exactly match the id-map above — it then
    // matches the category LABEL by substring, the way the old used-vinyl-only
    // code did, so a label like "Used Vinyl > Rock" still resolves.
    window.categoryCostPatterns = (function (rules) {
        var flat = [];
        (rules || []).forEach(function (r) {
            (r.match || []).forEach(function (p) {
                if (p) flat.push({ p: String(p).toLowerCase(), c: Number(r.cost) });
            });
        });
        flat.sort(function (a, b) { return b.p.length - a.p.length; });
        return flat;
    })(@json($category_cost_rules ?? []));

    // Pre-fill a row's Purchase Price from its category default, but only when
    // the field is still empty or zero — never clobber a cost the operator
    // typed. Reads the row's category combo directly. Returns true when a
    // default was applied.
    window.applyCategoryDefaultPurchasePrice = function (jqRow) {
        var $combo = jqRow.find('.category-combo-select').first();
        if (!$combo.length) return false;
        var $pp = jqRow.find('input[name*="[single_dpp_inc_tax]"]').first();
        if (!$pp.length) return false;
        var current = parseFloat(($pp.val() || '').toString().replace(/,/g, ''));
        if (!isNaN(current) && current > 0) return false; // keep operator's cost

        var cost = null;

        // 1) Precise: combo value is "<parentCategoryId>_<subId>"; cost keys on
        //    the parent category id.
        var parentId = String($combo.val() || '').split('_')[0];
        var defaults = window.categoryCostDefaults || {};
        if (parentId && Object.prototype.hasOwnProperty.call(defaults, parentId)) {
            cost = Number(defaults[parentId]);
        } else {
            // 2) Fallback: match the parent name in the option label (the part
            //    before " > ") against the alias patterns, longest first.
            var label = ($combo.find('option:selected').text() || '').toLowerCase();
            var parentName = label.split('>')[0].trim();
            if (parentName) {
                for (var i = 0; i < window.categoryCostPatterns.length; i++) {
                    if (parentName.indexOf(window.categoryCostPatterns[i].p) !== -1) {
                        cost = window.categoryCostPatterns[i].c;
                        break;
                    }
                }
            }
        }

        if (cost === null || isNaN(cost)) return false;
        $pp.val(cost.toFixed(2));
        return true;
    };
</script>
<script src="{{ asset('js/product.js?v=' . $asset_v) }}"></script>
<script src="{{ asset('js/mass_add_category_match.js?v=' . (file_exists(public_path('js/mass_add_category_match.js')) ? filemtime(public_path('js/mass_add_category_match.js')) : 1)) }}"></script>

<script type="text/javascript">
    window.isAddingNewRow = false;
    $(document).ready(function(){
        (function massAddArtistClickOnlySuggestions($) {
            var sugUrl = @json(route('products.autocompleteSuggestions'));
            var debounceMs = 220;
            var timers = {};
            var $floatPanel = $('#mass-add-artist-floating-panel');
            var $activeInput = $();

            function positionFloatingArtistPanel() {
                if (!$floatPanel.is(':visible') || !$activeInput.length || !$activeInput[0].getBoundingClientRect) {
                    return;
                }
                var r = $activeInput[0].getBoundingClientRect();
                var w = Math.max(r.width, 200);
                var top = r.bottom + 2;
                var maxH = 260;
                var spaceBelow = window.innerHeight - top - 8;
                var flipUp = spaceBelow < 120 && r.top > 140;
                if (flipUp) {
                    $floatPanel.css({
                        position: 'fixed',
                        left: Math.round(r.left) + 'px',
                        top: 'auto',
                        bottom: Math.round(window.innerHeight - r.top + 2) + 'px',
                        width: Math.round(w) + 'px',
                        maxHeight: Math.min(maxH, Math.round(r.top - 12)) + 'px'
                    });
                } else {
                    $floatPanel.css({
                        position: 'fixed',
                        left: Math.round(r.left) + 'px',
                        top: Math.round(top) + 'px',
                        bottom: 'auto',
                        width: Math.round(w) + 'px',
                        maxHeight: Math.min(maxH, Math.max(80, spaceBelow)) + 'px'
                    });
                }
            }

            function hideArtistFloatingPanel() {
                $floatPanel.hide().empty().attr('aria-hidden', 'true');
                $activeInput = $();
            }

            function showArtistFloatingPanel($input, items) {
                $floatPanel.empty();
                if (!items || !items.length) {
                    hideArtistFloatingPanel();
                    return;
                }
                $activeInput = $input;
                $.each(items, function (_, item) {
                    var text = item && (item.label != null ? item.label : item.value);
                    text = text == null ? '' : String(text);
                    $('<button type="button" class="mass-add-artist-option"/>')
                        .text(text)
                        .data('artistValue', text)
                        .appendTo($floatPanel);
                });
                $floatPanel.attr('aria-hidden', 'false').show();
                positionFloatingArtistPanel();
            }

            $(window).on('resize scroll', function () {
                positionFloatingArtistPanel();
            });
            $(document).on('scroll', '.responsive-table, .content-wrapper, .content', function () {
                positionFloatingArtistPanel();
            });

            $(document).on('input', '#product_rows_container .mass-add-artist-input', function () {
                var $input = $(this);
                var tid = $input.attr('id') || 'mass-artist';
                if (timers[tid]) {
                    clearTimeout(timers[tid]);
                }
                var q = ($input.val() || '').trim();
                if (q.length < 1) {
                    hideArtistFloatingPanel();
                    return;
                }
                timers[tid] = setTimeout(function () {
                    $.getJSON(sugUrl, { type: 'artist', q: q, limit: 20 })
                        .done(function (data) {
                            if (($input.val() || '').trim() !== q) {
                                return;
                            }
                            showArtistFloatingPanel($input, Array.isArray(data) ? data : []);
                        })
                        .fail(function () {
                            hideArtistFloatingPanel();
                        });
                }, debounceMs);
            });

            $(document).on('mousedown', '#mass-add-artist-floating-panel .mass-add-artist-option', function (e) {
                e.preventDefault();
                e.stopPropagation();
                var val = $(this).data('artistValue');
                if ($activeInput.length) {
                    $activeInput.val(val).trigger('change').focus();
                }
                hideArtistFloatingPanel();
            });

            $(document).on('keydown', '#product_rows_container .mass-add-artist-input', function (e) {
                if (e.keyCode === 27) {
                    hideArtistFloatingPanel();
                }
            });

            $(document).on('click', function (e) {
                if ($(e.target).closest('.mass-add-artist-wrap').length) {
                    return;
                }
                if ($(e.target).closest('#mass-add-artist-floating-panel').length) {
                    return;
                }
                hideArtistFloatingPanel();
            });
        })(jQuery);

        let rowIndex = 1;

        // Resolve the next data-row-index to use. Reading the last row's
        // attribute is robust to rows added via bulk paste, which would
        // otherwise collide with the local rowIndex counter.
        function nextRowIndex() {
            const $last = $('#product_rows_container .product-row').last();
            const lastAttr = parseInt($last.attr('data-row-index'), 10);
            const fromDom = isNaN(lastAttr) ? 0 : lastAttr + 1;
            const next = Math.max(rowIndex, fromDom);
            rowIndex = next + 1;
            return next;
        }

        // Append one fresh product row. Returns a Promise that resolves
        // once the row is in the DOM and its select2 widgets are ready.
        function appendOneProductRow() {
            return new Promise(function(resolve, reject) {
                const idx = nextRowIndex();
                $.ajax({
                    url: "{{ route('product.getMassProductRow') }}",
                    type: 'GET',
                    data: { index: idx },
                    success: function (row) {
                        $('#product_rows_container').append(row);
                        const $newRow = $('#product_rows_container .product-row').last();
                        $newRow.find('.select2').select2();
                        applyCategoryComboSelect2Matcher($newRow);
                        window.setupProductNameSelect2();
                        resolve($newRow);
                    },
                    error: function () {
                        reject();
                    },
                });
            });
        }

        // Add a new row
        $('#add_row').on('click', function () {
            if (window.isAddingNewRow) {
                return;
            }
            window.isAddingNewRow = true;
            $(this).html('Adding row...');
            appendOneProductRow()
                .then(function() {
                    window.isAddingNewRow = false;
                    $('#add_row').html('Add New Product Row');
                })
                .catch(function() {
                    toastr.error('Failed to add a new row.');
                    window.isAddingNewRow = false;
                    $('#add_row').html('Add New Product Row');
                });
        });

        // Add 5 rows at once
        $('#add_5_rows').on('click', function () {
            if (window.isAddingNewRow) {
                return;
            }
            window.isAddingNewRow = true;
            const $btn = $(this);
            const originalLabel = $btn.html();
            const total = 5;
            let added = 0;
            let failed = 0;

            function addNext() {
                if (added + failed >= total) {
                    window.isAddingNewRow = false;
                    $btn.html(originalLabel);
                    if (failed > 0) {
                        toastr.warning('Added ' + added + ' of ' + total + ' rows.');
                    }
                    return;
                }
                $btn.html('Adding row ' + (added + failed + 1) + ' of ' + total + '...');
                appendOneProductRow()
                    .then(function() { added++; addNext(); })
                    .catch(function() { failed++; addNext(); });
            }

            addNext();
        });

        // Copy down feature.
        // Use closest()+nextAll() instead of .eq()/.slice() so this works
        // regardless of how rows were added (manual, bulk-paste) or whether
        // earlier rows were removed — DOM order is the source of truth, not
        // the data-row-index attribute (which can drift after bulk-add or delete).
        $(document).on('click', '.copy-down', function() {
            const $btn = $(this);
            const inputClass = $btn.attr('data-class');
            if (!inputClass) return;
            const $sourceRow = $btn.closest('.product-row');
            if (!$sourceRow.length) return;

            const $sourceField = $sourceRow.find(`.${inputClass}`).first();
            const value = $sourceField.val();

            $sourceRow.nextAll('.product-row').each(function() {
                $(this).find(`.${inputClass}`).val(value).trigger('change');
            });
        });

        // Remove row (also drops any attached sold-before sub-row beneath it).
        $(document).on('click', '.remove_row', function () {
            const $tr = $(this).closest('.tr');
            const idx = $tr.attr('data-row-index');
            if (idx !== undefined) {
                $(`tr.discogs-sold-before-row[data-row-index="${idx}"]`).remove();
            }
            $tr.remove();
        });

        // When merged Category/Subcategory combo changes, sync hidden ids
        $(document).on('change', '.category-combo-select', function () {
            const $this = $(this);
            const rowIndex = $this.attr('data-row-index');
            const selected = $this.find('option:selected');
            const categoryId = selected.data('category-id') || '';
            const subCategoryId = selected.data('sub-category-id') || 0;

            $(`#products_${rowIndex}_category_id`).val(categoryId);
            $(`#products_${rowIndex}_sub_category_id`).val(subCategoryId);

            // Record/Sleeve grade only for vinyl/CD/cassette/etc. — lock and
            // clear it for DVDs, toys, gear and other non-music categories.
            const noGrade = selected.length && selected.val() !== '' && String(selected.data('graded')) === '0';
            $this.closest('.tr, .product-row').find('.massadd_condition, .massadd_sleeve_condition').each(function () {
                if (noGrade) $(this).val('');
                $(this).prop('disabled', noGrade).attr('title', noGrade ? 'No grade for this category' : $(this).hasClass('massadd_condition') ? 'Media Grade' : 'Sleeve Grade');
            });

            // Pre-fill the default purchase price for the chosen category
            // (only when the operator hasn't already typed a cost). Covers
            // manual category picks as well as the Discogs/artist auto-fills.
            window.applyCategoryDefaultPurchasePrice($this.closest('.tr, .product-row'));
        });

        // Auto-fill category from the store's curated Artist→Bin list when an
        // operator types/picks a known artist — same sheet that overrides
        // Discogs genres in the bulk-IDs flow. Only fills when the category
        // combo is still empty, so it never clobbers a manual choice.
        $(document).on('change', '.mass-add-artist-input', function () {
            const $input = $(this);
            const $row = $input.closest('.tr, .product-row');
            const $combo = $row.find('.category-combo-select');
            if (!$combo.length || ($combo.val() || '') !== '') {
                return; // operator already chose a category — leave it alone
            }
            const artist = ($input.val() || '').trim();
            if (!artist) return;

            $.getJSON("{{ route('product.massCreate.resolveArtistCategory') }}", { artist: artist })
                .done(function (res) {
                    if (!res || !res.matched || !res.category_id) return;
                    if (($combo.val() || '') !== '') return; // raced with a manual pick
                    const comboVal = res.category_id + '_' + (res.sub_category_id || 0);
                    const $opt = $combo.find('option[value="' + comboVal + '"]');
                    if ($opt.length) {
                        $combo.val(comboVal).trigger('change');
                        window.applyCategoryDefaultPurchasePrice($row);
                    }
                });
        });

        // Store valid subcategory IDs for each category
        window.categorySubcategories = {};

        // Handle category change to fetch subcategories
        $(document).on('change', '.category-select', function () {
            const $this = $(this);
            const category_id = $this.val();
            const rowIndex = $this.attr('data-row-index');
            const subCategorySelect = $this.closest('.tr').find('.subcategory-select');
            const $row = $this.closest('.tr');

            // Verify category immediately
            verifyCategorySubcategoryMatch($row, rowIndex);

            // window.getProductPriceRecommendation($(this).attr('data-row-index')); // DISABLED: eBay/Discogs suggestions

            if (category_id) {
                // Check if category exists in options
                const categoryOption = $this.find('option[value="' + category_id + '"]');
                if (categoryOption.length === 0 || !category_id || category_id === '') {
                    // Invalid category
                    verifyCategorySubcategoryMatch($row, rowIndex);
                    return;
                }
                
                $.ajax({
                    url: "{{ route('product.get_sub_categories') }}",
                    type: 'POST',
                    data: { cat_id: category_id },
                    headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
                    success: function (data) {
                        subCategorySelect.html(data);
                        
                        // Store valid subcategory IDs for this category
                        const validSubcategoryIds = [];
                        // Parse the HTML string to extract option values
                        const $temp = $('<div>').html(data);
                        $temp.find('option').each(function() {
                            const val = $(this).val();
                            if (val && val !== '') {
                                validSubcategoryIds.push(val);
                            }
                        });
                        window.categorySubcategories[category_id] = validSubcategoryIds;
                        
                        // Reinitialize Select2
                        subCategorySelect.trigger('change');
                        
                        // Verify current subcategory selection after a short delay to ensure Select2 is updated
                        setTimeout(function() {
                            verifyCategorySubcategoryMatch($row, rowIndex);
                        }, 100);
                    },
                    error: function () {
                        toastr.error('Failed to fetch subcategories.');
                        verifyCategorySubcategoryMatch($row, rowIndex);
                    },
                });
            } else {
                subCategorySelect.html('<option value="">@lang("messages.please_select")</option>');
                subCategorySelect.trigger('change');
                verifyCategorySubcategoryMatch($row, rowIndex);
            }
        });

        // Verify category/subcategory match
        function verifyCategorySubcategoryMatch($row, rowIndex) {
            const categorySelect = $row.find('.category-select');
            const categoryId = categorySelect.val();
            const subcategoryId = $row.find('.subcategory-select').val();
            
            // Remove existing indicators
            $row.find('.category-validation-indicator').remove();
            $row.find('.subcategory-validation-indicator').remove();
            
            // Check if category exists in dropdown options
            const categoryOption = categorySelect.find('option[value="' + categoryId + '"]');
            const categoryExists = categoryOption.length > 0 && categoryId && categoryId !== '';
            
            // Add validation indicator for category
            const categoryContainer = $row.find('.category-selection-container');
            if (!categoryContainer.find('.category-validation-indicator').length) {
                categoryContainer.append('<span class="category-validation-indicator" style="margin-left: 5px;"></span>');
            }
            
            if (!categoryId || categoryId === '') {
                // Category is optional; clear validation state when empty
                categorySelect.removeClass('is-invalid');
                $row.find('.subcategory-select').removeClass('is-invalid');
                $row.find('.category-validation-indicator').html('');
                return;
            }
            
            if (!categoryExists) {
                // Category doesn't exist in dropdown (invalid)
                $row.find('.category-validation-indicator').html('<i class="fa fa-times-circle text-danger" title="Invalid category - not found in system"></i>');
                categorySelect.addClass('is-invalid');
                return;
            } else {
                categorySelect.removeClass('is-invalid');
            }
            
            // Category is valid
            $row.find('.category-validation-indicator').html('<i class="fa fa-check-circle text-success" title="Valid category"></i>');
            
            if (subcategoryId) {
                // Check if subcategory belongs to category
                const validSubcategories = window.categorySubcategories[categoryId] || [];
                const isValid = validSubcategories.includes(subcategoryId);
                
                const subcategoryContainer = $row.find('.subcategory-select').parent();
                if (!subcategoryContainer.find('.subcategory-validation-indicator').length) {
                    subcategoryContainer.append('<span class="subcategory-validation-indicator" style="margin-left: 5px;"></span>');
                }
                
                const indicator = $row.find('.subcategory-validation-indicator');
                if (isValid) {
                    indicator.html('<i class="fa fa-check-circle text-success" title="Valid subcategory for this category"></i>');
                    $row.find('.subcategory-select').removeClass('is-invalid');
                } else {
                    indicator.html('<i class="fa fa-exclamation-triangle text-danger" title="This subcategory does not belong to the selected category"></i>');
                    $row.find('.subcategory-select').addClass('is-invalid');
                }
            } else {
                // No subcategory selected, but category is valid
                $row.find('.subcategory-select').removeClass('is-invalid');
            }
        }

        // Handle subcategory change to verify match
        $(document).on('change', '.subcategory-select', function () {
            const $this = $(this);
            const rowIndex = $this.closest('.tr').find('.category-select').attr('data-row-index');
            const $row = $this.closest('.tr');
            verifyCategorySubcategoryMatch($row, rowIndex);
        });
        
        // Validate categories on page load and when Select2 is opened/closed
        $(document).on('select2:open select2:close', '.category-select', function() {
            const $row = $(this).closest('.tr');
            const rowIndex = $(this).attr('data-row-index');
            setTimeout(function() {
                verifyCategorySubcategoryMatch($row, rowIndex);
            }, 100);
        });
        
        // Also validate when Select2 selection changes (for manual entry)
        $(document).on('select2:select select2:unselect', '.category-select', function() {
            const $row = $(this).closest('.tr');
            const rowIndex = $(this).attr('data-row-index');
            setTimeout(function() {
                verifyCategorySubcategoryMatch($row, rowIndex);
            }, 100);
        });

        // Verify all rows
        $('#verify_all_categories').on('click', function() {
            const rows = $('#product_rows_container .product-row');
            let validCount = 0;
            let invalidCount = 0;
            let missingSubcategoryCount = 0;
            const invalidRows = [];

            rows.each(function() {
                const $row = $(this);
                const rowIndex = $row.find('.category-select').attr('data-row-index');
                const categoryId = $row.find('.category-select').val();
                const subcategoryId = $row.find('.subcategory-select').val();
                const productName = $row.find('.product-name-autocomplete').val();

                // Verify this row
                verifyCategorySubcategoryMatch($row, rowIndex);

                if (!categoryId) {
                    // Category is optional; count this as valid if no category is selected.
                    validCount++;
                } else if (subcategoryId) {
                    // Check if subcategory belongs to category
                    const validSubcategories = window.categorySubcategories[categoryId] || [];
                    if (validSubcategories.includes(subcategoryId)) {
                        validCount++;
                    } else {
                        invalidCount++;
                        invalidRows.push({
                            row: rowIndex,
                            product: productName || 'Unnamed product',
                            issue: 'Subcategory does not belong to selected category'
                        });
                    }
                } else {
                    missingSubcategoryCount++;
                    // Category selected but no subcategory - this is OK, just count it
                    validCount++;
                }
            });

            // Show summary
            let summaryHtml = '<div class="alert alert-info"><h4><i class="fa fa-info-circle"></i> Verification Summary</h4>';
            summaryHtml += '<ul style="margin-bottom: 0;">';
            summaryHtml += `<li><strong>Valid:</strong> <span class="text-success">${validCount}</span> rows</li>`;
            if (invalidCount > 0) {
                summaryHtml += `<li><strong>Invalid:</strong> <span class="text-danger">${invalidCount}</span> rows (subcategory mismatch)</li>`;
            }
            if (missingSubcategoryCount > 0) {
                summaryHtml += `<li><strong>Missing Subcategory:</strong> <span class="text-info">${missingSubcategoryCount}</span> rows (category selected but no subcategory)</li>`;
            }
            summaryHtml += '</ul>';

            if (invalidRows.length > 0) {
                summaryHtml += '<hr><h5>Issues Found:</h5><ul>';
                invalidRows.forEach(item => {
                    summaryHtml += `<li>Row ${parseInt(item.row) + 1}: "${item.product}" - ${item.issue}</li>`;
                });
                summaryHtml += '</ul>';
            }
            summaryHtml += '</div>';

            // Show in a modal or alert
            swal({
                title: 'Category Verification Complete',
                content: {
                    element: 'div',
                    attributes: {
                        innerHTML: summaryHtml
                    }
                },
                icon: invalidCount > 0 ? 'warning' : 'success',
                buttons: {
                    confirm: {
                        text: 'OK',
                        className: 'btn btn-primary'
                    }
                }
            });
        });

        // Tooltip для элементов
        $('[data-toggle="tooltip"]').tooltip();


        // Tooltip initialization for dynamically added elements
        $(document).on('mouseenter', '[data-toggle="tooltip"]', function () {
            $(this).tooltip('show');
        });

        // Реинициализация Select2 для уже существующих строк
        $('.select2').select2();

        // Improved category/subcategory search:
        // tokenized prefix matching against the combo label (e.g. "used rock" => "Used Vinyl - Rock")
        function __massadd_tokenize_prefix_words(text) {
            if (text === undefined || text === null) return [];
            return String(text)
                .toLowerCase()
                .trim()
                .split(/[^a-z0-9]+/g)
                .filter(Boolean);
        }

        function __massadd_category_combo_matcher(params, data) {
            if (!data || !data.text) return data;

            var term = params && params.term ? String(params.term).trim().toLowerCase() : '';
            if (term === '') return data;

            var labelText = String(data.text || '').toLowerCase();
            var tokens = __massadd_tokenize_prefix_words(term);
            if (tokens.length === 0) return data;

            var words = labelText.match(/[a-z0-9]+/g) || [];
            var matchedAll = tokens.every(function (tok) {
                if (!tok) return true;
                return labelText.indexOf(tok) !== -1 || words.some(function (w) { return w.indexOf(tok) === 0; });
            });

            return matchedAll ? data : null;
        }

        function applyCategoryComboSelect2Matcher($scope) {
            var $root = $scope && $scope.length ? $scope : $('#product_rows_container');
            $root.find('select.category-combo-select').each(function () {
                var $el = $(this);
                var currentVal = $el.val();
                try {
                    if ($el.data('select2')) {
                        $el.select2('destroy');
                    }
                } catch (e) {
                    // ignore; keep default if re-init fails
                }

                $el.select2({
                    matcher: __massadd_category_combo_matcher
                });

                if (currentVal !== null && currentVal !== undefined && currentVal !== '') {
                    $el.val(currentVal).trigger('change.select2');
                }
            });
        }

        applyCategoryComboSelect2Matcher($('#product_rows_container'));

        // Note: opening stock/location-level stock editing has been removed from mass-add.

        $(document).on('click', '.show-expandables', function() {
            if ($(this).hasClass('show')) {
                $('.expandable').hide();    
            } else {
                $('.expandable').css('display', 'table-cell');
            }

            $(this).toggleClass('show');
        });

        $(document).on('change', 'input[type="file"]', function () {
            const fileName = $(this).val().split('\\').pop();
            $(this).siblings('.custom-file-label').addClass("selected").html(fileName);
        });

        $(document).on('click', '.btn-remove-product-selection', function() {
            window.setAsFreeTextProductRow($(this).attr('data-row-index'));
        });

        window.massAddThenSendToPurchase = false;

        function runMassAddSave(sendToPurchase) {
            window.massAddThenSendToPurchase = !!sendToPurchase;
            $('#save_all_products').trigger('click');
        }

        $('#save_and_send_to_purchase').on('click', function(e) {
            e.preventDefault();
            runMassAddSave(true);
        });

        // Обработка клика по кнопке "Save All Products" с отладкой
        $('#save_all_products').on('click', function(e){
            e.preventDefault();  // Предотвращаем стандартную отправку формы

            // Guard against double-submits: a save can take ~20s, and staff were
            // re-clicking thinking the page had hung.
            if (window.__massAddSubmitting) {
                return false;
            }

            // Underpricing guard (Sarah 2026-11-17): check every row against
            // Discogs / our sales / eBay before saving. Re-enters this handler
            // with __priceGuardOk set once staff fix prices or give a reason.
            if (!window.__priceGuardOk) {
                window.massAddPriceGuardThenSave();
                return false;
            }
            window.__priceGuardOk = false;

            // Clear previous error messages
            $('.error-message').remove();
            $('.is-invalid').removeClass('is-invalid');
            
            // Validate all categories before submission
            let hasErrors = false;
            let errorMessages = [];
            const rows = $('#product_rows_container .product-row');
            
            rows.each(function(index) {
                const $row = $(this);
                const rowIndex = $row.attr('data-row-index');
                const categorySelect = $row.find('.category-select');
                const categoryId = categorySelect.val();
                const subcategorySelect = $row.find('.subcategory-select');
                const subcategoryId = subcategorySelect.val();
                const productName = $row.find('.product-name-autocomplete').val() || `Product ${parseInt(rowIndex) + 1}`;
                
                // Category is optional; validate only when provided
                if (categoryId && categoryId !== '') {
                    // Check if category exists in dropdown options
                    const categoryOption = categorySelect.find('option[value="' + categoryId + '"]');
                    if (categoryOption.length === 0) {
                        hasErrors = true;
                        categorySelect.addClass('is-invalid');
                        const errorMsg = `Row ${parseInt(rowIndex) + 1} (${productName}): Invalid category - not found in system`;
                        errorMessages.push(errorMsg);
                        categorySelect.closest('td').append(`<div class="invalid-feedback error-message" style="display: block; color: red; font-size: 12px;">Invalid category - not found in system</div>`);
                    } else if (subcategoryId && subcategoryId !== '') {
                        // Check subcategory if selected
                        const validSubcategories = window.categorySubcategories[categoryId] || [];
                        if (!validSubcategories.includes(subcategoryId)) {
                            hasErrors = true;
                            subcategorySelect.addClass('is-invalid');
                            const errorMsg = `Row ${parseInt(rowIndex) + 1} (${productName}): Subcategory does not belong to selected category`;
                            errorMessages.push(errorMsg);
                            subcategorySelect.closest('td').append(`<div class="invalid-feedback error-message" style="display: block; color: red; font-size: 12px;">Subcategory does not belong to selected category</div>`);
                        }
                    }
                }
            });
            
            // If validation errors found, show them and prevent submission
            if (hasErrors) {
                toastr.error('Please fix the following errors before saving:\n' + errorMessages.join('\n'), 'Validation Errors', {
                    timeOut: 10000,
                    extendedTimeOut: 10000
                });
                
                // Scroll to first error
                const firstError = $('.is-invalid').first();
                if (firstError.length) {
                    $('html, body').animate({
                        scrollTop: firstError.offset().top - 100
                    }, 500);
                }
                
                return false;
            }

            let form = $('#mass_create_form')[0];
            let formData = new FormData(form);  // Собираем все данные формы

            // console.log('Submitting form data...');
            // for (let pair of formData.entries()) {
            //     console.log(pair[0]+ ': ' + pair[1]);
            // }

            $.ajax({
                url: $('#mass_create_form').attr('action'),
                type: $('#mass_create_form').attr('method'),
                data: formData,
                processData: false,
                contentType: false,
                beforeSend: function() {
                    window.__massAddSubmitting = true;
                    $('#save_all_products, #save_and_send_to_purchase').prop('disabled', true);
                    $('#mass_add_saving_overlay').css('display', 'flex');
                },
                complete: function() {
                    window.__massAddSubmitting = false;
                    $('#save_all_products, #save_and_send_to_purchase').prop('disabled', false);
                    $('#mass_add_saving_overlay').hide();
                },
                success: function(response) {
                    if(response.success) {
                        toastr.success(response.msg);
                        // toastr.success already triggers the gentle chime via
                        // common.js — no extra .play() needed (it'd be loud).

                        // Duplicate-barcode guard: rows skipped because their
                        // SKU/barcode already belongs to an existing product.
                        // Nothing was auto-merged — staff add stock to the
                        // existing listing instead. Show each one so it's not
                        // silently lost, and hold the redirect long enough to
                        // actually be seen.
                        var skippedDupes = response.duplicates_skipped || [];
                        skippedDupes.forEach(function (d) {
                            toastr.warning(
                                'Row ' + d.row + ' "' + d.name + '" (SKU ' + d.sku + ') already exists as "' +
                                d.existing_product_name + '" — skipped. Open it and add stock instead: ' +
                                d.existing_product_url,
                                'Duplicate skipped',
                                { timeOut: 0, extendedTimeOut: 0 }
                            );
                        });

                        const product_ids = response.product_ids;
                        var fromPurchase = window !== window.top && /from_purchase=1/.test(window.location.search);
                        if (fromPurchase && product_ids && product_ids.length) {
                            window.parent.postMessage({ type: 'massAddComplete', product_ids: product_ids }, '*');
                            return;
                        }
                        if (window.massAddThenSendToPurchase && product_ids && product_ids.length) {
                            window.massAddThenSendToPurchase = false;
                            var baseUrl = "{{ url('') }}";
                            window.location.href = baseUrl + '/purchases/create?from_products=' + product_ids.join(',');
                            return;
                        }
                        setTimeout(() => {
                            if (window.confirm("Do you want to print the labels?")) {
                                window.location.href = `/labels/show?product_ids=${product_ids.join(",")}`;
                            } else {
                                window.location.href = `/products`;
                            }
                        }, skippedDupes.length ? 2500 : 300);
                    } else {
                        toastr.error('Error: ' + (response.error || response.msg));
                        document.getElementById('error-audio').play();
                    }
                },
                error: function(xhr) {
                    if (xhr.status === 422) {
                        // Handle validation errors
                        let errors = xhr.responseJSON.errors;
                        let errorMessages = [];
                        
                        // Clear previous error messages
                        $('.error-message').remove();
                        $('.is-invalid').removeClass('is-invalid');
                        
                        // Helper function to add error message
                        function addError(inputSelector, errorMessage) {
                            let $input = $(inputSelector);
                            if ($input.length) {
                                $input.addClass('is-invalid');
                                let $errorDiv = $('<div>')
                                    .addClass('invalid-feedback error-message')
                                    .text(errorMessage);
                                $input.closest('td').append($errorDiv);
                            }
                        }
                        
                        // Make field keys human-readable for the popup. Known fields
                        // get a friendly label; anything else is title-cased.
                        const fieldLabels = {
                            single_dsp_inc_tax: 'Selling Price',
                            name: 'Product name',
                            business_locations: 'Business location',
                            category_id: 'Category',
                            artist: 'Artist',
                            stock: 'Stock quantity'
                        };
                        function humanizeField(fieldName) {
                            if (!fieldName) return 'Field';
                            if (fieldLabels[fieldName]) return fieldLabels[fieldName];
                            return fieldName
                                .replace(/_/g, ' ')
                                .replace(/\b\w/g, function(c) { return c.toUpperCase(); });
                        }

                        // Strip Laravel's ugly attribute path from the default message
                        // (e.g. "The products.0.unit_price field is required.").
                        function cleanMessage(msg, fieldName) {
                            return String(msg).replace(/products\.\d+\.[a-z_]+/gi, humanizeField(fieldName).toLowerCase());
                        }

                        let rowErrors = [];

                        // Process each error
                        Object.keys(errors).forEach(function(key) {
                            // Extract product index and field name from the key (e.g., "products.0.name")
                            let parts = key.split('.');
                            let productIndex = parts[1];
                            let fieldName = parts[2];

                            // Add error message based on field type
                            if (fieldName === 'business_locations') {
                                addError(`[name="products[${productIndex}][business_locations][]"]`, errors[key][0]);
                            } else {
                                addError(`[name="products[${productIndex}][${fieldName}]"]`, errors[key][0]);
                            }

                            // Add to error messages array for toastr
                            errorMessages.push(errors[key][0]);

                            rowErrors.push({
                                row: parseInt(productIndex, 10),
                                field: humanizeField(fieldName),
                                msg: cleanMessage(errors[key][0], fieldName)
                            });
                        });

                        // Build a clear popup that tells staff exactly which row /
                        // field is wrong and what's missing.
                        if (rowErrors.length > 0) {
                            rowErrors.sort(function(a, b) { return a.row - b.row; });

                            let html = '<div class="alert alert-danger" style="text-align:left; margin-bottom:0;">';
                            html += '<p style="margin-bottom:8px;"><strong>Please fix the following before saving:</strong></p>';
                            html += '<ul style="margin-bottom:0; padding-left:20px;">';
                            rowErrors.forEach(function(item) {
                                html += `<li><strong>Row ${item.row + 1} — ${item.field}:</strong> ${item.msg}</li>`;
                            });
                            html += '</ul></div>';

                            swal({
                                title: 'Some products are missing information',
                                content: {
                                    element: 'div',
                                    attributes: { innerHTML: html }
                                },
                                icon: 'error',
                                buttons: {
                                    confirm: { text: 'Go fix it', className: 'btn btn-danger' }
                                }
                            });

                            try { document.getElementById('error-audio').play(); } catch (e) {}

                            // Scroll to the first invalid field so they can find it fast.
                            let $firstError = $('.is-invalid').first();
                            if ($firstError.length) {
                                $('html, body').animate({ scrollTop: $firstError.offset().top - 120 }, 400);
                            }
                        }
                    } else {
                        toastr.error('An unexpected error occurred. Please try again.');
                        try { document.getElementById('error-audio').play(); } catch (e) {}
                    }
                }
            });
        });

        window.setupProductNameSelect2();
    
    // Ensure all buttons are properly bound (in case of duplicate IDs or timing issues)
    console.log('Mass create page initialized');
    console.log('Parse button found:', $('#parse_bulk_text').length);
    console.log('Preview button found:', $('#preview_bulk_text').length);
    console.log('Clear button found:', $('#clear_bulk_text').length);
    console.log('Format button found:', $('#format_bulk_text').length);
    });

    window.setAsFreeTextProductRow = function(rowIndex) {
        $(`.btn-remove-product-selection[data-row-index="${rowIndex}"]`).remove();
        $(`.product-name-autocomplete[data-row-index="${rowIndex}"]`).val("").prop('readonly', false);
        $(`.product-row[data-row-index="${rowIndex}"] .select2_business_locations`).val(null).trigger('change');
        $(`.product-row[data-row-index="${rowIndex}"] .product-id`).val('');
        $(`.product-row[data-row-index="${rowIndex}"] .variation-id`).val('');
    }

    window.setAsSelectedProductRow = function(ui, input) {
        const rowIndex = input.attr('data-row-index');
        const item = ui.item;
        const openingLocations = item.opening_locations || [];
        const locationIds = openingLocations.map(n => n.id);

        $(`.btn-remove-product-selection[data-row-index="${rowIndex}"]`).remove();

        input.val(ui.item.text).prop('readonly', true);

        input.after(`<button type="button" class="btn btn-xs btn-remove-product-selection" data-row-index="${rowIndex}" style="min-width: 40px; font-size: 15px;">
            <i class="fa fa-times-circle"></i>
        </button>`);

        const $row = $(`.product-row[data-row-index="${rowIndex}"]`);

        $row.find('input.sku-input').first().val(item.sub_sku || '');

        var catId = item.category_id != null && item.category_id !== '' ? String(item.category_id) : '';
        var subId = item.sub_category_id != null && item.sub_category_id !== '' ? String(item.sub_category_id) : '0';
        if (catId === '') {
            subId = '0';
        }
        var comboVal = catId + '_' + subId;
        var $combo = $row.find('select.category-combo-select').first();
        if ($combo.length && catId !== '') {
            if ($combo.find('option[value="' + comboVal + '"]').length) {
                $combo.val(comboVal).trigger('change');
            } else {
                var matched = '';
                $combo.find('option').each(function() {
                    var $o = $(this);
                    if (String($o.attr('data-category-id') || '') === catId &&
                        String($o.attr('data-sub-category-id') || '') === subId) {
                        matched = String($o.val() || '');
                        return false;
                    }
                });
                if (matched) {
                    $combo.val(matched).trigger('change');
                } else {
                    $(`#products_${rowIndex}_category_id`).val(catId);
                    $(`#products_${rowIndex}_sub_category_id`).val(subId);
                }
            }
        }

        var sp = item.sell_price_inc_tax;
        if (sp !== null && sp !== undefined && sp !== '') {
            var spNum = parseFloat(sp);
            if (!isNaN(spNum)) {
                $row.find('input[name*="[single_dsp_inc_tax]"]').first().val(spNum.toFixed(2));
            }
        }
        var pp = item.dpp_inc_tax;
        if (pp !== null && pp !== undefined && pp !== '') {
            var ppNum = parseFloat(pp);
            if (!isNaN(ppNum)) {
                $row.find('input[name*="[single_dpp_inc_tax]"]').first().val(ppNum.toFixed(4));
            }
        }
        if (item.artist) {
            $row.find('input[name*="[artist]"]').first().val(item.artist);
        }

        $row.find('.select2_business_locations').val(locationIds).trigger('change');
        $row.find('.product-id').val(item.product_id);
        $row.find('.variation-id').val(item.variation_id);

        // Run keyword rules + Product Entry Rules (title → artist/prices/cat; cat/sub → prices) without hiding columns
        setTimeout(function() {
            if (typeof window.runProductEntryRulesForMassAddName === 'function') {
                window.runProductEntryRulesForMassAddName($row.find('.product-name-autocomplete').first());
            }
        }, 50);
    }

    // DISABLED: eBay/Discogs price recommendations removed to reduce row height
    // window.discogsReleasesData = [];
    // window.getProductPriceRecommendation = (function() { ... })(); // REMOVED
    
    // Subcategory suggestions still work via separate API call
    window.getSubcategorySuggestions = (function() {
        let timeout;
        return function(rowIndex) {
            clearTimeout(timeout);
            const productName = $(".product-name-autocomplete[data-row-index='" + rowIndex + "']").val();
            const categoryId = $(`#products_${rowIndex}_category_id`).val();
            const subCategorySuggestionsContainer = $(`.sub-category-suggestions-container[data-row-index='${rowIndex}']`);
            subCategorySuggestionsContainer.html("");

            timeout = setTimeout(function() {
                $.getJSON('/product/mass-create/get-product-price-recommendation', {
                    query: productName,
                    category_id: categoryId,
                    row_index: rowIndex
                }, function(response) {
                    rowIndex = response.row_index;
                    const discogs_price_recommendation_sub_categories = response.discogs_price_recommendation_sub_categories;
                    if (discogs_price_recommendation_sub_categories && discogs_price_recommendation_sub_categories.length > 0) {
                        subCategorySuggestionsContainer.html(`
                            <div class="sub-category-suggestion-item">
                                <h4>Subcategory Suggestions</h4>
                                ${discogs_price_recommendation_sub_categories.map(subCategory => `<span class="sub-category-suggestion-item-name">${subCategory}</span>`).join('')}
                            </div>
                        `);
                    } else {
                        subCategorySuggestionsContainer.html("");
                    }
                });
            }, 500);
        };
    })();

    $(document).on('keyup', '.product-name-autocomplete', function() {
        window.getSubcategorySuggestions($(this).attr('data-row-index'));
    });

    $(document).on('keyup', '.sku-input', function() {
        window.getSubcategorySuggestions($(this).attr('data-row-index'));
    });

    $(document).on('click', '#clear_all_skus', function(e) {
        e.preventDefault();
        $('#product_rows_container').find('input.sku-input').val('').trigger('change');
    });

    window.setupProductNameSelect2 = function () {
        try {
            $(".product-name-autocomplete").each(function () {
                $(this).autocomplete({
                    source: function(request, response) {
                        $.getJSON('/product/mass-create/get-products', { term: request.term }, response);
                    },
                    minLength: 3,
                    autoFocus: true,
                    response: function(event, ui) {
                        if (ui.content.length == 1) {
                            // Auto-select if only one result
                            setTimeout(() => {
                                $(this).data('ui-autocomplete').menu.activate();
                                $(this).data('ui-autocomplete').menu.select();
                            }, 100);
                        } else if (ui.content.length == 0) {
                            var term = $(this).data('ui-autocomplete').term;
                            
                            // swal({
                            //     title: LANG.no_products_found,
                            //     text: __translate('add_name_as_new_product', { term: term }),
                            //     buttons: [LANG.cancel, LANG.ok],
                            // }).then(value => {
                            //     if (value) {
                            //         var container = $('.quick_add_product_modal');
                            //         $.ajax({
                            //             url: '/products/quick_add?product_name=' + term,
                            //             dataType: 'html',
                            //             success: function(result) {
                            //                 $(container)
                            //                     .html(result)
                            //                     .modal('show');
                            //             },
                            //         });
                            //     }
                            // });
                        }
                    },
                    select: function(event, ui) {
                        event.preventDefault();
                        $(this).val(ui.item.text);
                            window.setAsSelectedProductRow(ui, $(this));
                        $(this).autocomplete('close');
                        return false;
                    },
                    focus: function(event, ui) {
                        event.preventDefault();
                        return false;
                    }
                }).autocomplete('instance')._renderItem = function(ul, item) {
                    return $('<li>').append('<div>' + item.text + '</div>').appendTo(ul);
                };
                
                // Handle Enter key to submit autocomplete
                $(this).on('keydown', function(event) {
                    if (event.keyCode === 13) { // Enter key
                        const autocomplete = $(this).data('ui-autocomplete');
                        if (autocomplete && autocomplete.menu.active) {
                            event.preventDefault();
                            autocomplete.menu.select();
                        }
                    }
                });
            });
                    
        } catch (error) {
            console.log("ERRROR : ", error);
        }
    }

    // Enhanced bulk text parsing functionality with smart format detection
    function parseBulkProductText(text) {
        function normalizeToken(s) {
            return String(s || '').toLowerCase().replace(/[^a-z0-9]+/g, ' ').trim();
        }

        function extractMoneyToken(rawLine) {
            const src = String(rawLine || '');
            // Match $10, $ 10.50, 10.50$, etc.
            let m = src.match(/\$\s*([0-9]+(?:\.[0-9]{1,4})?)/);
            if (m && m[1]) return m[1];
            m = src.match(/\b([0-9]+(?:\.[0-9]{1,4})?)\s*\$/);
            if (m && m[1]) return m[1];
            return '';
        }

        function cleanMoney(v) {
            return String(v || '').replace(/[$,]/g, '').trim();
        }

        function looksLikeMoney(v) {
            const c = cleanMoney(v);
            return c !== '' && /^[0-9]+(?:\.[0-9]{1,4})?$/.test(c);
        }

        function inferLocationToken(rawLine) {
            const lower = String(rawLine || '').toLowerCase();
            if (lower.indexOf('pico') !== -1) return 'Pico';
            if (lower.indexOf('hollywood') !== -1) return 'Hollywood';
            return '';
        }

        function normalizeParsedProductFields(product) {
            // Pull price from any field that looks like a money token.
            const priceCandidates = [
                product.price,
                product.sku,
                product.listing_location,
                extractMoneyToken(product.raw_line)
            ];
            let finalPrice = '';
            for (let i = 0; i < priceCandidates.length; i++) {
                if (looksLikeMoney(priceCandidates[i])) {
                    finalPrice = cleanMoney(priceCandidates[i]);
                    break;
                }
            }
            if (finalPrice) {
                product.price = finalPrice;
            }

            // Fix common shifted CSV case:
            // Product, Artist, Category, Subcategory, $10, pico
            // where $10 was incorrectly assigned to SKU and pico to price.
            const locationFromRaw = inferLocationToken(product.raw_line);
            const locationFromFields =
                inferLocationToken(product.listing_location) ||
                inferLocationToken(product.price) ||
                inferLocationToken(product.sku);
            const finalLocation = locationFromFields || locationFromRaw;
            if (finalLocation) {
                product.listing_location = finalLocation;
            }

            // If SKU is actually money/location text, clear it.
            if (looksLikeMoney(product.sku) || inferLocationToken(product.sku)) {
                product.sku = '';
            }
            // If price field still contains location text, keep numeric price only.
            if (!looksLikeMoney(product.price) && finalPrice) {
                product.price = finalPrice;
            }
        }

        function inferHintsFromRawLine(rawLine, product) {
            const lower = String(rawLine || '').toLowerCase();

            // If user types $10, treat it as selling price by default.
            if (!product.price) {
                const m = lower.match(/\$\s*([0-9]+(?:\.[0-9]{1,4})?)/);
                if (m && m[1]) {
                    product.price = m[1];
                }
            }

            // Lightweight genre/category inference from natural text.
            const genreMap = [
                { keys: ['used vinyl', 'used'], category: 'used vinyl' },
                { keys: ['vinyl'], category: 'vinyl' },
                { keys: ['r&b', 'rnb', 'r and b'], subcategory: 'r&b' },
                { keys: ['rock'], subcategory: 'rock' },
                { keys: ['jazz'], subcategory: 'jazz' },
                { keys: ['hip hop', 'hiphop'], subcategory: 'hip hop' },
                { keys: ['soul'], subcategory: 'soul' }
            ];
            genreMap.forEach(function(g) {
                const hit = g.keys.some(function(k) { return lower.indexOf(k) !== -1; });
                if (!hit) return;
                if (!product.category && g.category) product.category = g.category;
                if (!product.subcategory && g.subcategory) product.subcategory = g.subcategory;
            });

            // Location inference from free text.
            if (!product.listing_location) {
                if (lower.indexOf('pico') !== -1) {
                    product.listing_location = 'Pico';
                } else if (lower.indexOf('hollywood') !== -1) {
                    product.listing_location = 'Hollywood';
                }
            }

            // If category field accidentally contains price symbols, clean it.
            if (product.category && /\$/.test(product.category)) {
                product.category = normalizeToken(product.category);
            }
        }

        // AI tools sometimes return the whole CSV list on one line, each row
        // ending "...,Price, Next Name,...". Break after "<number>, " so each
        // row becomes its own line (in-row fields have no space after commas).
        text = text.split('\n').map(function (line) {
            if (line.split(',').length <= 8) return line;
            return line.replace(/,(\d+(?:\.\d+)?),[ \t]+(?=\S)/g, ',$1,\n');
        }).join('\n');

        const lines = text.split('\n').filter(line => line.trim() !== '');
        const products = [];
        
        lines.forEach((line, index) => {
            line = line.trim();
            if (!line || line.startsWith('//') || line.startsWith('#')) return; // Skip comments
            // Skip a pasted header line ("Product Name,Artist,Category,...") — ChatGPT echoes it.
            if (/^\s*"?(product\s*)?(name|title)"?\s*[,|\t]\s*"?artist\b/i.test(line)) return;
            
            let product = {
                name: '',
                artist: '',
                category: '',
                subcategory: '',
                sku: '',
                price: '',
                bin_position: '',
                listing_location: '',
                condition: '',
                raw_line: line,
                lineNumber: index + 1
            };
            
            // Detect format by checking delimiters
            const hasPipe = line.includes('|');
            const hasComma = line.includes(',');
            const hasTab = line.includes('\t');
            const hasDash = line.includes(' - ') || line.includes(' – '); // Regular dash or en-dash
            const hasMultipleSpaces = /\s{2,}/.test(line);
            
            // Priority: Tab > Pipe > Comma > Multiple Spaces > Dash > Simple
            if (hasTab) {
                // Tab-delimited format
                const parts = line.split('\t').map(p => p.trim());
                product.name = parts[0] || '';
                product.condition = parts[8] || ''; // optional 9th: media grade
                product.artist = parts[1] || '';
                product.category = parts[2] || '';
                product.subcategory = parts[3] || '';
                product.sku = parts[4] || '';
                product.price = parts[5] || '';
                product.bin_position = parts[6] || '';
                product.listing_location = parts[7] || '';
            }
            else if (hasPipe) {
                // Pipe-delimited format: Product | Artist | Category | Subcategory | SKU | Price | Bin | Listing Location
                const parts = line.split('|').map(p => p.trim());
                product.name = parts[0] || '';
                product.condition = parts[8] || ''; // optional 9th: media grade (Store or Online? sends it)
                product.artist = parts[1] || '';
                product.category = parts[2] || '';
                product.subcategory = parts[3] || '';
                product.sku = parts[4] || '';
                product.price = parts[5] || '';
                product.bin_position = parts[6] || '';
                product.listing_location = parts[7] || '';
            }
            else if (hasComma && line.split(',').length >= 2) {
                // CSV format: Product,Artist,Category,Subcategory,SKU,Price,Bin,Listing Location
                // Handle quoted CSV values
                const parts = [];
                let current = '';
                let inQuotes = false;
                
                for (let i = 0; i < line.length; i++) {
                    const char = line[i];
                    if (char === '"') {
                        inQuotes = !inQuotes;
                    } else if (char === ',' && !inQuotes) {
                        parts.push(current.trim());
                        current = '';
                    } else {
                        current += char;
                    }
                }
                parts.push(current.trim()); // Add last part
                
                product.name = parts[0] || '';
                product.artist = parts[1] || '';
                product.category = parts[2] || '';
                product.subcategory = parts[3] || '';
                product.sku = parts[4] || '';
                product.price = parts[5] || '';
                product.bin_position = parts[6] || '';
                product.listing_location = parts[7] || '';
            }
            else if (hasMultipleSpaces) {
                // Multiple spaces as delimiter (common in copied text)
                const parts = line.split(/\s{2,}/).map(p => p.trim());
                product.name = parts[0] || '';
                product.artist = parts[1] || '';
                product.category = parts[2] || '';
                product.subcategory = parts[3] || '';
                product.sku = parts[4] || '';
                product.price = parts[5] || '';
                product.bin_position = parts[6] || '';
                product.listing_location = parts[7] || '';
            }
            else if (hasDash) {
                // Dash format: Product - Artist or Product – Artist
                const dashIndex = line.indexOf(' - ') !== -1 ? line.indexOf(' - ') : line.indexOf(' – ');
                product.name = line.substring(0, dashIndex).trim();
                product.artist = line.substring(dashIndex + 3).trim();
            }
            else {
                // Just product name
                product.name = line;
            }
            
            // Clean up price (remove $, commas, etc.)
            if (product.price) {
                product.price = product.price.replace(/[$,]/g, '').trim();
            }

            inferHintsFromRawLine(line, product);
            normalizeParsedProductFields(product);
            
            if (product.name) {
                products.push(product);
            }
        });
        
        return products;
    }
    
    // ---- Underpricing guard (Sarah 2026-11-17) ----------------------------
    // Server compares each row's price to Discogs (sales-based value for its
    // grade + cheapest copy listed), our own sales, and eBay asking prices.
    function priceGuardItem($row) {
        const price = $.trim($row.find('input[name*="[single_dsp_inc_tax]"]').val() || '');
        if (!(parseFloat(price) > 0)) return null;
        return {
            idx: $row.attr('data-row-index'),
            price: price,
            name: $.trim($row.find('.product-name-autocomplete').val() || ''),
            artist: $.trim($row.find('input[name*="[artist]"]').val() || ''),
            sku: $.trim($row.find('input[name*="[sku]"]').val() || ''),
            discogs_release_id: $.trim($row.find('input[name*="[discogs_release_id]"]').val() || ''),
            condition: $row.find('.massadd_condition').val() || '',
            category_id: $row.find('.category-select').val() || '',
            category: $.trim($row.find('.category-combo-select option:selected').text() || ''),
        };
    }

    function runPriceGuard(items) {
        return new Promise(function (resolve) {
            if (!items.length) return resolve({});
            $.ajax({
                url: "{{ route('product.massCreate.priceGuard') }}",
                type: 'POST',
                data: JSON.stringify({ items: items }),
                contentType: 'application/json',
                headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
                timeout: 240000,
                success: function (resp) { resolve((resp && resp.results) || {}); },
                error: function () { resolve(null); },
            });
        });
    }

    function priceGuardSources(r) {
        const bits = [];
        if (r.discogs) {
            const d = r.discogs;
            let t = '<a href="https://www.discogs.com/sell/release/' + d.release_id + '" target="_blank">Discogs</a>';
            if (d.value) t += ' ' + $('<div>').text(d.grade).html() + ' $' + Number(d.value).toFixed(2);
            if (d.lowest) t += ', cheapest $' + Number(d.lowest).toFixed(2);
            bits.push(t);
        }
        if (r.store) bits.push('our sales $' + Number(r.store.median).toFixed(2) + ' (' + r.store.sales + ')');
        if (r.ebay && r.ebay.median) bits.push('<a href="' + r.ebay.uri + '" target="_blank">eBay</a> asking $' + Number(r.ebay.median).toFixed(2));
        return bits.join(' &middot; ');
    }

    function markPriceGuard($row, r) {
        const $price = $row.find('input[name*="[single_dsp_inc_tax]"]');
        $price.siblings('.price-guard-note').remove();
        if (!r || !r.flag) {
            if ($price.data('priceGuardRed')) $price.css({ 'border-color': '', 'background': '' }).data('priceGuardRed', false);
            return;
        }
        $price.css({ 'border-color': '#dc2626', 'background': '#fef2f2' }).data('priceGuardRed', true);
        $('<div class="price-guard-note" style="font-size:11px;color:#b91c1c;line-height:1.2;margin-top:2px;"></div>')
            .html('Too low? ' + priceGuardSources(r) + ' &rarr; <a href="#" class="price-guard-use" data-price="' + r.suggested + '">use $' + Number(r.suggested).toFixed(2) + '</a>')
            .insertAfter($price);
    }
    window.markPriceGuard = markPriceGuard;

    $(document).on('click', '.price-guard-use', function (e) {
        e.preventDefault();
        const $price = $(this).closest('td').find('input[name*="[single_dsp_inc_tax]"]');
        $price.val(Number($(this).data('price')).toFixed(2)).trigger('change');
    });

    // Inline check when a price is typed/changed (debounced per row).
    const priceGuardTimers = {};
    $(document).on('change', '#product_rows_container input[name*="[single_dsp_inc_tax]"]', function () {
        const $row = $(this).closest('.product-row');
        const idx = $row.attr('data-row-index');
        clearTimeout(priceGuardTimers[idx]);
        priceGuardTimers[idx] = setTimeout(function () {
            const item = priceGuardItem($row);
            if (!item) { markPriceGuard($row, null); return; }
            runPriceGuard([item]).then(function (res) {
                if (!res) return;
                const cur = priceGuardItem($row);
                if (cur && cur.price === item.price) markPriceGuard($row, res[item.idx]);
            });
        }, 700);
    });

    // Runs before every save. Flagged rows → modal: take the suggested price
    // or keep yours with a reason. Then re-triggers the save.
    window.massAddPriceGuardThenSave = function () {
        const items = [];
        const rowsByIdx = {};
        $('#product_rows_container .product-row').each(function () {
            const it = priceGuardItem($(this));
            if (it) { items.push(it); rowsByIdx[it.idx] = $(this); }
        });
        const proceed = function () { window.__priceGuardOk = true; $('#save_all_products').trigger('click'); };
        if (!items.length) return proceed();

        const $btns = $('#save_all_products, #save_and_send_to_purchase');
        const oldHtml = $('#save_all_products').html();
        $btns.prop('disabled', true);
        $('#save_all_products').html('<i class="fa fa-spinner fa-spin"></i> Checking prices vs Discogs/eBay&hellip;');
        runPriceGuard(items).then(function (res) {
            $btns.prop('disabled', false);
            $('#save_all_products').html(oldHtml);
            if (!res) {
                if (confirm('Could not check prices against Discogs/eBay right now. Save anyway?')) proceed();
                return;
            }
            const flagged = items.filter(function (it) { return res[it.idx] && res[it.idx].flag; });
            items.forEach(function (it) { markPriceGuard(rowsByIdx[it.idx], res[it.idx]); });
            if (!flagged.length) return proceed();

            const esc = function (v) { return $('<div>').text(v == null ? '' : String(v)).html(); };
            let rowsHtml = '';
            flagged.forEach(function (it) {
                const r = res[it.idx];
                rowsHtml += '<tr data-idx="' + esc(it.idx) + '">'
                    + '<td><input type="checkbox" class="pg-use" checked></td>'
                    + '<td><strong>' + esc(it.artist ? it.artist + ' - ' : '') + esc(it.name) + '</strong>'
                    + (it.condition ? ' <span class="text-muted">' + esc(it.condition) + '</span>' : '')
                    + '<div style="font-size:11px;">' + priceGuardSources(r) + '</div></td>'
                    + '<td style="color:#b91c1c;font-weight:700;">$' + Number(it.price).toFixed(2) + '</td>'
                    + '<td><input type="number" step="0.01" min="0" class="form-control input-sm pg-new" style="width:90px;" value="' + Number(r.suggested).toFixed(2) + '"></td>'
                    + '</tr>';
            });
            $('#price_guard_modal').remove();
            const $m = $('<div class="modal fade" id="price_guard_modal" tabindex="-1" role="dialog"><div class="modal-dialog modal-lg" role="document"><div class="modal-content">'
                + '<div class="modal-header"><h4 class="modal-title"><i class="fa fa-exclamation-triangle" style="color:#dc2626;"></i> ' + flagged.length + ' item' + (flagged.length > 1 ? 's are' : ' is') + ' priced well under market</h4></div>'
                + '<div class="modal-body"><p>These prices are much lower than what they sell for on Discogs, eBay, or in our store. Ticked rows switch to the suggested price.</p>'
                + '<table class="table table-condensed"><thead><tr><th></th><th>Item</th><th>Your price</th><th>Use</th></tr></thead><tbody>' + rowsHtml + '</tbody></table>'
                + '<div id="pg_reason_wrap" style="display:none;"><label>Why keep the low price on the unticked rows? (required)</label>'
                + '<input type="text" class="form-control" id="pg_reason" placeholder="e.g. damaged cover, bargain bin, matching another copy"></div></div>'
                + '<div class="modal-footer"><button type="button" class="btn btn-default" data-dismiss="modal">Go back</button>'
                + '<button type="button" class="btn btn-success" id="pg_apply">Use these prices &amp; save</button></div>'
                + '</div></div></div>');
            $('body').append($m);
            const syncReason = function () { $('#pg_reason_wrap').toggle($m.find('.pg-use:not(:checked)').length > 0); };
            $m.on('change', '.pg-use', syncReason);
            $m.on('click', '#pg_apply', function () {
                const kept = [];
                $m.find('tbody tr').each(function () {
                    const $tr = $(this);
                    const idx = $tr.data('idx');
                    const $row = rowsByIdx[idx];
                    if ($tr.find('.pg-use').is(':checked')) {
                        const v = parseFloat($tr.find('.pg-new').val());
                        if (v > 0) {
                            $row.find('input[name*="[single_dsp_inc_tax]"]').val(v.toFixed(2));
                            markPriceGuard($row, null);
                        }
                    } else {
                        kept.push($.trim($tr.find('td').eq(1).find('strong').text()));
                    }
                });
                const reason = $.trim($('#pg_reason').val() || '');
                if (kept.length && reason.length < 3) {
                    $('#pg_reason').focus().css('border-color', '#dc2626');
                    return;
                }
                $('#mass_create_form input[name="price_guard_reason"]').remove();
                if (kept.length) {
                    $('<input type="hidden" name="price_guard_reason">').val(reason + ' | kept: ' + kept.join('; ')).appendTo('#mass_create_form');
                }
                $m.modal('hide');
                proceed();
            });
            $m.on('hidden.bs.modal', function () { $m.remove(); });
            $m.modal('show');
        });
    };

    // Past-sales lookup for parsed rows. Also returns store_price: when our
    // store has consistently sold the same item for more than the pasted (AI)
    // price, store_price.suggested is the price we actually get (Sarah 2026-11-15).
    function lookupBulkPastSales(products) {
        const items = products.map((p, i) => ({
            idx:         i,
            name:        p.name || '',
            sku:         p.sku  || '',
            artist:      p.artist || '',
            category:    p.category || '',
            subcategory: p.subcategory || '',
            condition:   p.condition || '',
            price:       p.price || '',
            discogs_release_id: p.discogs_release_id || '',
        }));
        // Native Promise; resolves null on failure (never rejects).
        return new Promise(function (resolve) {
            $.ajax({
                url: "{{ route('product.massCreate.pastSalesLookup') }}",
                type: 'POST',
                data: JSON.stringify({ items: items }),
                contentType: 'application/json',
                headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
                timeout: 180000,
                success: function (resp) { resolve((resp && resp.results) || {}); },
                error: function () { resolve(null); },
            });
        });
    }

    // Green price + "was $X" note on a table row whose price we raised.
    function markStorePriceRaised($input, fromPrice, toPrice, note) {
        $input.css({ 'border-color': '#16a34a', 'background': '#f0fdf4' })
              .attr('title', 'Raised from $' + fromPrice + '. ' + note);
        $input.siblings('.store-price-raised').remove();
        $('<div class="store-price-raised" style="font-size:11px;color:#15803d;line-height:1.2;margin-top:2px;"></div>')
            .text('was $' + fromPrice + ' \u2014 we sell at $' + toPrice)
            .attr('title', note)
            .insertAfter($input);
    }

    function storePriceNote(sp) {
        return 'Our store sold this ' + sp.sales + '\u00d7 (median $' + Number(sp.median).toFixed(2)
            + ', $' + Number(sp.low).toFixed(2) + '\u2013$' + Number(sp.high).toFixed(2) + ')';
    }

    // Show preview of parsed products
    function showBulkPreview(products) {
        const container = $('#bulk_preview_container');
        const table = $('#bulk_preview_table');
        const count = $('#bulk_preview_count');

        if (products.length === 0) {
            container.hide();
            return;
        }

        count.text(products.length);

        // Stable token so async lookup only updates the latest render.
        const renderToken = (window.__bulkPreviewToken || 0) + 1;
        window.__bulkPreviewToken = renderToken;

        function escapeHtml(s) {
            return String(s == null ? '' : s)
                .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
        }

        let html = '<table class="table table-bordered table-sm" style="margin-bottom: 0;">';
        html += '<thead><tr><th>#</th><th>Name</th><th>Artist</th><th>Category</th><th>SKU</th><th>Price</th><th>Bin</th><th>Sold before?</th></tr></thead><tbody>';

        products.forEach((product, index) => {
            html += `<tr>
                <td>${index + 1}</td>
                <td>${escapeHtml(product.name) || '<span class="text-muted">-</span>'}</td>
                <td>${escapeHtml(product.artist) || '<span class="text-muted">-</span>'}</td>
                <td>${escapeHtml(product.category) || '<span class="text-muted">-</span>'}</td>
                <td>${escapeHtml(product.sku) || '<span class="text-muted">-</span>'}</td>
                <td class="bulk-preview-price-cell" data-row-index="${index}">${product.price ? '$' + escapeHtml(product.price) : '<span class="text-muted">-</span>'}</td>
                <td>${escapeHtml(product.bin_position) || '<span class="text-muted">-</span>'}</td>
                <td class="bulk-preview-sold-cell" data-row-index="${index}"><span class="text-muted"><i class="fa fa-spinner fa-spin"></i></span></td>
            </tr>`;
        });

        html += '</tbody></table>';
        table.html(html);
        container.show();

        // Fire generic past-sales lookup (name fuzzy + SKU exact). Best-effort:
        // a failure here leaves cells as a muted dash and never blocks add.
        lookupBulkPastSales(products).then(function (results) {
                if (window.__bulkPreviewToken !== renderToken) return; // stale render
                if (!results) {
                    table.find('.bulk-preview-sold-cell').html('<span class="text-muted">—</span>');
                    return;
                }
                Object.keys(results).forEach(function (k) {
                    const r = results[k];
                    const sp = r && r.store_price;
                    if (sp && sp.suggested) {
                        table.find('.bulk-preview-price-cell[data-row-index="' + k + '"]').html(
                            '<s class="text-muted">$' + escapeHtml(products[k].price) + '</s> '
                            + '<strong style="color:#15803d;">$' + Number(sp.suggested).toFixed(2) + '</strong>'
                            + '<div style="font-size:11px;color:#15803d;" title="' + escapeHtml(storePriceNote(sp)) + '">'
                            + 'we sell higher: ' + sp.above + '/' + sp.sales + ' sales</div>'
                        );
                    }
                    const $cell = table.find('.bulk-preview-sold-cell[data-row-index="' + k + '"]');
                    if (!$cell.length) return;

                    if (!r || !r.count) {
                        $cell.html('<span class="text-muted">—</span>');
                        return;
                    }

                    const tooltip = (r.samples || [])
                        .map(function (s) { return s.name + (s.sku ? ' (' + s.sku + ')' : ''); })
                        .join('\n');
                    const lastTxt = r.last_sold ? ' • last ' + r.last_sold : '';
                    $cell.html(
                        '<span class="label label-success" title="' + escapeHtml(tooltip) + '">'
                        + 'Sold ' + r.count + '×' + escapeHtml(lastTxt)
                        + '</span>'
                    );
                });
        });
    }

    function addProductFromParsedData(productData, rowIndex) {
        function normalizeForMatch(s) {
            return String(s || '').toLowerCase().replace(/[^a-z0-9]+/g, ' ').trim();
        }

        function tokenize(s) {
            const norm = normalizeForMatch(s);
            if (!norm) return [];
            // Drop short stop-words; keep useful tokens like "lp", "cd", "7", "12"
            return norm.split(/\s+/).filter(function(t) {
                return t.length > 0 && t !== 'and' && t !== 'the';
            });
        }

        // Score a single option label against a set of hint tokens.
        // Higher score = better match. Bidirectional: counts hint tokens
        // present in label AND label tokens present in hint.
        function scoreOptionForHint(labelTokens, hintTokens, weight) {
            if (!hintTokens.length || !labelTokens.length) return 0;
            const labelSet = {};
            labelTokens.forEach(function(t) { labelSet[t] = true; });
            const hintSet = {};
            hintTokens.forEach(function(t) { hintSet[t] = true; });

            let matches = 0;
            Object.keys(hintSet).forEach(function(t) {
                if (labelSet[t]) matches++;
            });
            if (!matches) return 0;
            // Reward higher coverage of hint tokens in label.
            const coverage = matches / Object.keys(hintSet).length;
            return weight * (matches + coverage);
        }

        // Sarah 2026-10-28: public/js/mass_add_category_match.js (vague hints
        // used to land on the first option: Trading Cards > Digimon, 7", 45 RPM).
        function findBestCategoryComboValue($combo, product) {
            if (!$combo || !$combo.length || typeof window.massAddCategoryMatch !== 'function') {
                return '';
            }
            const options = $combo.find('option').map(function() {
                return { value: String($(this).val() || ''), label: $.trim($(this).text()) };
            }).get();
            return window.massAddCategoryMatch(options, product);
        }

        function findLocationIdsByText($locations, hintText) {
            const q = normalizeForMatch(hintText);
            if (!q) return [];
            const ids = [];
            $locations.find('option').each(function() {
                const $opt = $(this);
                const text = normalizeForMatch($opt.text());
                if (text && (text.indexOf(q) !== -1 || q.indexOf(text) !== -1)) {
                    ids.push(String($opt.val()));
                }
            });
            return ids;
        }

        return new Promise((resolve) => {
            $.ajax({
                url: "{{ route('product.getMassProductRow') }}",
                type: 'GET',
                data: { index: rowIndex },
                success: function (row) {
                    const $row = $(row);
                    // Sarah 2026-05-06: bulk-paste rows go to the TOP, not the bottom.
                    // Caller reverses the parsed array so iterating + prepending preserves
                    // the user's typed order (first-typed row ends up at the very top).
                    $('#product_rows_container').prepend($row);
                    
                    // Fill in the data
                    const rowSelector = `.product-row[data-row-index="${rowIndex}"]`;
                    
                    // Product name
                    $row.find('.product-name-autocomplete').val(productData.name || '');
                    
                    // Artist
                    $row.find('input[name*="[artist]"]').val(productData.artist || '');
                    
                    // SKU
                    $row.find('input[name*="[sku]"]').val(productData.sku || '');
                    
                    // Bin position
                    if (productData.bin_position) {
                        $row.find('input[name*="[bin_position]"]').val(productData.bin_position);
                    }
                    
                    // Listing location
                    if (productData.listing_location) {
                        $row.find('input[name*="[listing_location]"]').val(productData.listing_location);
                        const $locations = $row.find('.select2_business_locations');
                        const locationIds = findLocationIdsByText($locations, productData.listing_location);
                        if (locationIds.length) {
                            $locations.val(locationIds).trigger('change');
                        }
                    }
                    
                    // Category/Subcategory: match against merged combo options.
                    const $combo = $row.find('.category-combo-select');
                    const comboVal = findBestCategoryComboValue($combo, productData);
                    if (comboVal) {
                        $combo.val(comboVal).trigger('change');
                    }

                    // Selling price (row field is single_dsp_inc_tax, not selling_price).
                    // data-parsed-price tells applyRuleToScope (product.js) not to overwrite
                    // the parsed value when a category_combo rule fires from the combo-change
                    // or blur triggers below.
                    if (productData.price) {
                        const $sp = $row.find('input[name*="[single_dsp_inc_tax]"]')
                            .val(productData.price)
                            .attr('data-parsed-price', '1');
                        if (productData.store_price_from) {
                            markStorePriceRaised($sp, productData.store_price_from, productData.price, productData.store_price_note);
                        }
                    }

                    // Media grade (optional 9th field): short grade or the full wording.
                    if (productData.condition) {
                        const GR = { 'SEALED': 'Sealed', 'M': 'Mint (M)', 'NM': 'Near Mint (NM or M-)', 'M-': 'Near Mint (NM or M-)', 'VG+': 'Very Good Plus (VG+)', 'VG': 'Very Good (VG)', 'G+': 'Good Plus (G+)', 'G': 'Good (G)', 'F': 'Fair (F)', 'P': 'Poor (P)' };
                        const raw = String(productData.condition).trim(), g = GR[raw.toUpperCase()] || raw;
                        const $cond = $row.find('.massadd_condition');
                        if ($cond.find('option').filter(function () { return this.value === g; }).length) $cond.val(g);
                    }

                    // Reinitialize Select2
                    $row.find('.select2').select2();
                    window.setupProductNameSelect2();
                    // Apply POS manual item price rules (window.manualItemPriceRules) + product entry rules from product.js
                    $row.find('.product-name-autocomplete').trigger('blur');
                    resolve();
                },
                error: function () {
                    console.error('Failed to add row for product:', productData.name);
                    resolve();
                }
            });
        });
    }

    // Real-time preview as user types (debounced)
    let previewTimeout;
    $('#bulk_product_text').on('input', function() {
        clearTimeout(previewTimeout);
        previewTimeout = setTimeout(() => {
            const text = $(this).val().trim();
            // Plain typed lists go through "Price it" — only preview already-formatted lists.
            if (text.length > 10 && /[,|\t]/.test(text)) {
                const products = parseBulkProductText(text);
                if (products.length > 0) {
                    showBulkPreview(products);
                } else {
                    $('#bulk_preview_container').hide();
                }
            }
        }, 500);
    });
    
    // Preview button
    $('#preview_bulk_text').on('click', function() {
        const bulkText = $('#bulk_product_text').val().trim();
        if (!bulkText) {
            toastr.warning('Please enter some product data to preview.');
            return;
        }
        
        const products = parseBulkProductText(bulkText);
        if (products.length === 0) {
            toastr.warning('No valid products found in the text.');
            $('#bulk_preview_container').hide();
            return;
        }
        
        showBulkPreview(products);
        toastr.info(`Found ${products.length} products. Review the preview below.`);
    });
    
    // Auto-format button
    $('#format_bulk_text').on('click', function() {
        const bulkText = $('#bulk_product_text').val().trim();
        if (!bulkText) {
            toastr.warning('Please enter some product data to format.');
            return;
        }
        
        const products = parseBulkProductText(bulkText);
        if (products.length === 0) {
            toastr.warning('No valid products found to format.');
            return;
        }
        
        // Format as pipe-delimited for consistency
        const formatted = products.map(p => {
            return [
                p.name || '',
                p.artist || '',
                p.category || '',
                p.subcategory || '',
                p.sku || '',
                p.price || '',
                p.bin_position || '',
                p.listing_location || ''
            ].concat(p.condition ? [p.condition] : []).join(' | ');
        }).join('\n');
        
        $('#bulk_product_text').val(formatted);
        toastr.success('Text formatted successfully!');
        showBulkPreview(products);
    });
    
    // Handle bulk text parsing
    $('#parse_bulk_text').on('click', function() {
        const bulkText = $('#bulk_product_text').val().trim();
        if (!bulkText) {
            toastr.warning('Please enter some product data to parse.');
            return;
        }
        
        const products = parseBulkProductText(bulkText);
        if (products.length === 0) {
            toastr.warning('No valid products found in the text.');
            return;
        }
        
        // Confirm before adding (skipped when "Price it" just filled the box)
        const skipConfirm = $(this).data('skipConfirm');
        $(this).data('skipConfirm', false);
        if (!skipConfirm && !confirm(`Are you sure you want to add ${products.length} products to the table?`)) {
            return;
        }
        
        removeBlankMassRows();
        $('#bulk_parse_status').html(`<i class="fa fa-spinner fa-spin"></i> Adding ${products.length} products...`);
        $(this).prop('disabled', true);
        $('#preview_bulk_text').prop('disabled', true);

        // Sarah 2026-05-06: rows are PREPENDED to the table now (see addProductFromParsedData).
        // Reverse the parsed list so each subsequent prepend pushes the previous one down,
        // ending with the first-typed product at the very top.
        products.reverse();

        // Raise AI-entered prices where our store consistently sells higher.
        // Best-effort: if the lookup fails, add rows with the pasted prices.
        function applyStorePrices() {
            return lookupBulkPastSales(products).then(function (results) {
                let raised = 0;
                if (!results) return 0;
                products.forEach(function (p, i) {
                    const sp = results[i] && results[i].store_price;
                    if (sp && sp.suggested && p.price) {
                        p.store_price_from = p.price;
                        p.store_price_note = storePriceNote(sp);
                        p.price = Number(sp.suggested).toFixed(2);
                        raised++;
                    }
                });
                return raised;
            });
        }

        let currentRowIndex = parseInt($('#product_rows_container .product-row').last().attr('data-row-index') || '0');
        let addedCount = 0;
        let errorCount = 0;
        
        // Add products sequentially to avoid overwhelming the server
        function addNextProduct(index) {
            if (index >= products.length) {
                const message = errorCount > 0 
                    ? `Added ${addedCount} products (${errorCount} errors).`
                    : `Successfully added ${addedCount} products!`;
                    
                $('#bulk_parse_status').html(`<span class="text-success"><i class="fa fa-check"></i> ${message}</span>`);
                $('#parse_bulk_text').prop('disabled', false);
                $('#preview_bulk_text').prop('disabled', false);
                
                if (raisedCount > 0) {
                    toastr.info(`Raised ${raisedCount} price${raisedCount === 1 ? '' : 's'} to what our store usually sells ${raisedCount === 1 ? 'it' : 'them'} for (green prices in the table).`, '', { timeOut: 10000 });
                }
                if (errorCount === 0) {
                    toastr.success(`Added ${addedCount} products from bulk text.`);
                    $('#bulk_product_text').val('');
                    $('#bulk_preview_container').hide();
                } else {
                    toastr.warning(message);
                }
                return;
            }
            
            currentRowIndex++;
            addProductFromParsedData(products[index], currentRowIndex)
                .then(() => {
                    addedCount++;
                    $('#bulk_parse_status').html(`<i class="fa fa-spinner fa-spin"></i> Adding ${addedCount}/${products.length} products...`);
                    setTimeout(() => addNextProduct(index + 1), 200); // Small delay between adds
                })
                .catch(() => {
                    errorCount++;
                    addedCount++;
                    $('#bulk_parse_status').html(`<i class="fa fa-spinner fa-spin"></i> Adding ${addedCount}/${products.length} products... (${errorCount} errors)`);
                    setTimeout(() => addNextProduct(index + 1), 200);
                });
        }
        
        let raisedCount = 0;
        $('#bulk_parse_status').html('<i class="fa fa-spinner fa-spin"></i> Checking our past sale prices...');
        applyStorePrices().then(function (n) {
            raisedCount = n;
            addNextProduct(0);
        });
    });

    // Rows sent from "Store or Online?" (/store-or-discogs, checkboxes → Send to
    // Mass Add): open the Bulk Product Entry box with them and show the preview;
    // staff check it and press Parse & Add as usual.
    (function () {
        if (!/[?&]from=store-or-online\b/.test(window.location.search)) return;
        var text = null, ids = null;
        try {
            text = localStorage.getItem('massAddFromStoreOrOnline'); localStorage.removeItem('massAddFromStoreOrOnline');
            ids = localStorage.getItem('massAddFromStoreOrOnlineDiscogs'); localStorage.removeItem('massAddFromStoreOrOnlineDiscogs');
        } catch (e) {}
        // Rows with a Discogs release ID: into the Discogs box and fetched, so
        // every field (cover, label, genre, grade...) fills in like typing IDs there.
        if (ids) setTimeout(function () {
            var $ids = $('#bulk_discogs_ids'), $dbox = $ids.closest('.box');
            $dbox.removeClass('collapsed-box').children('.box-body').show();
            $ids.val(ids).trigger('input');
            $('html, body').animate({ scrollTop: $dbox.offset().top - 60 }, 200);
            $('#fetch_discogs_ids').trigger('click');
        }, 800);
        if (!text) return;
        // After the page's own load-time input clearing.
        setTimeout(function () {
            var $ta = $('#bulk_product_text'), $box = $ta.closest('.box');
            $box.removeClass('collapsed-box').children('.box-body').show();
            $ta.val(text);
            showBulkPreview(parseBulkProductText(text));
            $('#bulk_parse_status').html('<span class="text-info"><i class="fa fa-share"></i> ' + text.split('\n').length + ' sent from Store or Online? Check the preview, then press <strong>Parse &amp; Add Products</strong>.</span>');
            if (!ids) $('html, body').animate({ scrollTop: $box.offset().top - 60 }, 200);
        }, 800);
    })();

    // Sarah 2026-11-15: "Price it & add to table" — rough typed list → Claude → CSV → table.
    $('#ai_price_bulk_text').on('click', function() {
        const $btn = $(this);
        const items = $('#bulk_product_text').val().trim();
        if (!items) {
            toastr.warning('Type your items in the box first, one per line.');
            $('#bulk_product_text').focus();
            return;
        }
        const n = items.split('\n').filter(l => l.trim()).length;
        $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Pricing ' + n + ' item' + (n === 1 ? '' : 's') + '...');
        $('#bulk_parse_status').html('This can take up to a minute for long lists.');
        $.ajax({
            url: "{{ route('product.massCreate.aiPrice') }}",
            method: 'POST',
            data: { items: items },
            headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
            timeout: 120000
        }).done(function(res) {
            if (!res || !res.ok) {
                $('#bulk_parse_status').html('<span class="text-danger">' + $('<div>').text((res && res.error) || 'Something went wrong.').html() + '</span>');
                return;
            }
            const esc = t => $('<div>').text(String(t)).html();
            const money = v => '$' + Number(v).toFixed(2);
            const hist = res.history || [];
            if (hist.length) {
                const rows = hist.map(h => {
                    if (h.card) {
                        return '<li><strong>' + esc(h.item) + '</strong>: online about ' + money(h.ai) + ', our ' + esc(h.kind) + ' usually sell for ' + money(h.median)
                            + ' &rarr; <strong style="color:#b45309;">met in the middle at ' + money(h.price) + '</strong></li>';
                    }
                    if (h.low) {
                        return '<li><strong>' + esc(h.item) + '</strong>: AI said ' + money(h.ai) + ', under what we get for ' + esc(h.kind) + ' (usually ' + money(h.median) + ')'
                            + ' &rarr; <strong style="color:#15803d;">raised to ' + money(h.price) + '</strong></li>';
                    }
                    const speed = h.days === null ? '' : (h.days <= 1 ? ', sells same day' : ', sells in ~' + h.days + ' days');
                    const raised = h.price > h.ai
                        ? ' &rarr; <strong style="color:#15803d;">raised ' + money(h.ai) + ' to ' + money(h.price) + '</strong>' + (h.fast ? ' (fast seller)' : '')
                        : ' &rarr; kept ' + money(h.price);
                    const matched = (h.match && h.item.toLowerCase().indexOf(String(h.match).toLowerCase()) !== 0) ? ' <span class="text-muted">(our &ldquo;' + esc(h.match) + '&rdquo;)</span>' : '';
                    return '<li><strong>' + esc(h.item) + '</strong>' + matched + ': sold ' + h.sales + 'x, usually ' + money(h.median) + speed
                        + (h.in_stock > 0 ? ', ' + h.in_stock + ' in stock now' : '') + raised + '</li>';
                }).join('');
                $('#ai_price_history').html('<strong><i class="fa fa-line-chart"></i> Our sales history</strong><ul style="margin:6px 0 0; padding-left:18px;">' + rows + '</ul>').show();
            } else {
                $('#ai_price_history').html('<span class="text-muted">None of these have sold in our store in the last 2 years, so prices are the AI&rsquo;s market estimate.</span>').show();
            }
            $('#bulk_product_text').val(res.csv);
            $('#bulk_preview_container').hide();
            $('#parse_bulk_text').data('skipConfirm', true).trigger('click');
        }).fail(function() {
            $('#bulk_parse_status').html('<span class="text-danger">Couldn\'t reach the server. Try again.</span>');
        }).always(function() {
            $btn.prop('disabled', false).html('<i class="fa fa-magic"></i> Price it &amp; add to table');
        });
    });

    // Clear bulk text
    $('#clear_bulk_text').on('click', function() {
        if (confirm('Are you sure you want to clear the bulk text entry?')) {
            $('#bulk_product_text').val('');
            $('#bulk_parse_status').html('');
            $('#bulk_preview_container').hide();
        }
    });

    // ============================================================
    //  Sarah 2026-05-06: Bulk Discogs Release IDs
    //  Paste IDs → server fetches each from Discogs → prepend a row.
    // ============================================================
    // Returns the next safe data-row-index across the WHOLE container.
    // Bug fix: previous version read .last() which after prepending always
    // returned the original tail row → every new index collided.
    // Drop untouched blank rows (the page's starting row) before a bulk add,
    // so "Parse & Add" / Discogs / preset adds don't leave an extra empty row.
    function removeBlankMassRows() {
        $('#product_rows_container .product-row').each(function() {
            const $tr = $(this);
            const filled = $tr.find('input[name$="[name]"], input[name$="[artist]"], input[name$="[sku]"], input[name$="[single_dsp_inc_tax]"], input[name$="[id]"]')
                .filter(function() { return ($(this).val() || '').trim() !== ''; }).length;
            if (filled) return;
            const idx = $tr.attr('data-row-index');
            if (idx !== undefined) $(`tr.discogs-sold-before-row[data-row-index="${idx}"]`).remove();
            $tr.remove();
        });
    }

    function nextMassRowIndex() {
        let max = -1;
        $('#product_rows_container .product-row').each(function() {
            const v = parseInt($(this).attr('data-row-index'), 10);
            if (!isNaN(v) && v > max) max = v;
        });
        return max + 1;
    }

    // Render the "sold before" + Discogs marketplace info as a full-width
    // sub-row beneath the product row, so we have the whole table width to
    // play with instead of the cramped 200px col-name cell. Two lenses
    // (artist-wide vs. this title) each with per-location/channel chips.
    // The sub-row tracks the product row by data-row-index so the remove
    // handler can drop it alongside the parent.
    function renderSalesHistoryBadge($row, discogsData) {
        const rowIdx = $row.attr('data-row-index');
        if (rowIdx === undefined) return;

        // Drop any previous sub-row for this index (e.g., a re-render).
        $(`tr.discogs-sold-before-row[data-row-index="${rowIdx}"]`).remove();

        const sh = discogsData && discogsData.sales_history;
        const so = discogsData && discogsData.stock_on_hand;
        const ms = discogsData && discogsData.marketplace_stats;

        // Strip the "In-store: " prefix the backend adds — the green colour
        // already signals in-store, so we don't need to repeat it.
        function shortLabel(b) {
            const lbl = (b.label || '').replace(/^In-store:\s*/i, '');
            return $('<div>').text(lbl).html();
        }
        const esc = (s) => $('<div>').text(s == null ? '' : String(s)).html();

        function renderLens(prefix, lens) {
            if (!lens || !lens.total_lines) {
                return `<span class="text-muted">${prefix}: <em>no prior sales</em></span>`;
            }
            // Plain bold-black chips on a subtle light pill — the green/blue
            // label-success/label-info fills were drowning out the $ revenue.
            // Channel (in-store vs online) is already obvious from the
            // location name ("Pico", "Hollywood", "Whatnot"), so no colored
            // background needed.
            const chips = (lens.by_channel || []).map(b =>
                `<span style="display:inline-block;margin:1px 3px 1px 0;padding:1px 7px;`
                + `background:#f3f4f6;border:1px solid #d1d5db;border-radius:4px;`
                + `color:#000;font-weight:700;font-size:12px;" `
                + `title="${shortLabel(b)} — qty ${b.qty}, $${Number(b.revenue).toFixed(2)} (first ${b.first || '—'}, last ${b.last || '—'})">`
                + `${shortLabel(b)} ×${b.qty} `
                + `<span style="font-weight:700;">$${Number(b.revenue).toFixed(2)}</span></span>`
            ).join('');
            const summary = `${lens.total_lines} line${lens.total_lines === 1 ? '' : 's'} · `
                + `$${Number(lens.total_revenue).toFixed(2)} total · last ${lens.last_sold || '—'}`;
            return `<strong>${prefix}:</strong> ${chips}`
                + ` <span class="text-muted" style="font-size:11px;">(${summary})</span>`;
        }

        // A business_location is considered "online" (virtual marketplace
        // inventory) if its name matches one of these patterns. Matches the
        // Nivessa convention where Discogs/eBay listings live in dedicated
        // "<Channel> Warehouse" virtual locations rather than the physical
        // stores. Online chips render blue (matches the sold-before Discogs
        // chip), physical chips render orange (warning).
        function isOnlineLocation(name) {
            return /\b(discogs|ebay|whatnot|reverb|shopify|online|warehouse)\b/i.test(name || '');
        }
        function prettyLocationName(name) {
            // "Discogs Warehouse" → "Discogs", "eBay Warehouse" → "eBay" — the
            // "Warehouse" suffix is implementation detail not worth the space.
            return (name || '').replace(/\s*Warehouse\s*$/i, '').trim() || name;
        }

        // Render current stock-on-hand by location, matching the bold-black
        // neutral-pill chip style used by the sold-before chips above so the
        // two sections read consistently.
        function renderStockLens(prefix, stock) {
            if (!stock || !stock.total_qty) {
                return `<strong>${prefix}:</strong> <span class="text-muted"><em>none in stock</em></span>`;
            }
            const chips = (stock.by_location || []).map(loc => {
                const display = prettyLocationName(loc.location_name);
                return `<span style="display:inline-block;margin:1px 3px 1px 0;padding:1px 7px;`
                    + `background:#f3f4f6;border:1px solid #d1d5db;border-radius:4px;`
                    + `color:#000;font-weight:700;font-size:12px;" `
                    + `title="${esc(loc.location_name)} — ${Number(loc.qty)} units across ${loc.lines} variation${loc.lines === 1 ? '' : 's'}">`
                    + `${esc(display)} ×${Number(loc.qty)}</span>`;
            }).join('');
            return `<strong>${prefix}:</strong> ${chips}`
                + ` <span class="text-muted" style="font-size:11px;">(${Number(stock.total_qty)} total)</span>`;
        }

        const sections = [];
        const artistName = esc(discogsData.artist || 'this artist');
        const titleName  = esc(discogsData.title  || 'this title');

        if (sh) {
            sections.push(
                `<div style="margin-bottom:3px;">`
                + `<span class="text-muted" style="font-size:10px;text-transform:uppercase;letter-spacing:0.5px;margin-right:6px;">`
                + `<i class="fa fa-history"></i> Sold before</span>`
                + `</div>`
            );
            sections.push(`<div style="margin-bottom:3px;">${renderLens('Artist (' + artistName + ')', sh.by_artist)}</div>`);
            sections.push(`<div style="margin-bottom:3px;">${renderLens('Title ('  + titleName  + ')', sh.by_title )}</div>`);
        }

        // Always render the stock section when the backend returned a stock
        // payload (even when total_qty is 0) so the cashier gets a definitive
        // "none in stock" answer — silently hiding it made never-sold,
        // never-stocked items look like the lookup was broken.
        if (so) {
            sections.push(
                `<div style="margin-top:6px;margin-bottom:3px;">`
                + `<span class="text-muted" style="font-size:10px;text-transform:uppercase;letter-spacing:0.5px;margin-right:6px;">`
                + `<i class="fa fa-cube"></i> Currently in stock</span>`
                + `</div>`
            );
            if (so.by_artist) {
                sections.push(`<div style="margin-bottom:3px;">${renderStockLens('Artist (' + artistName + ')', so.by_artist)}</div>`);
            }
            if (so.by_title) {
                sections.push(`<div style="margin-bottom:3px;">${renderStockLens('Title ('  + titleName  + ')', so.by_title )}</div>`);
            }
        }

        if (ms) {
            const bits = [];
            if (ms.have != null) bits.push(`Have <strong>${esc(ms.have)}</strong>`);
            if (ms.want != null) bits.push(`Want <strong>${esc(ms.want)}</strong>`);
            if (ms.lowest_price != null) bits.push(`Lowest listed <strong>$${Number(ms.lowest_price).toFixed(2)}</strong>`);
            if (ms.num_for_sale != null) bits.push(`<strong>${esc(ms.num_for_sale)}</strong> for sale`);
            if (bits.length) {
                sections.push(
                    `<div class="text-muted" style="font-size:11px;margin-top:2px;">`
                    + `<i class="fa fa-globe"></i> Discogs marketplace: ${bits.join(' &nbsp;·&nbsp; ')}`
                    + `</div>`
                );
            }
        }

        if (!sections.length) return;

        // Figure out the column span by counting visible cells in the parent row.
        const colspan = $row.children('td').length || 13;

        const subRow = `<tr class="discogs-sold-before-row" data-row-index="${rowIdx}">`
            + `<td colspan="${colspan}" `
            +   `style="background:#fafbfc;border-top:1px dashed #e0e0e0;padding:6px 12px;font-size:12px;line-height:1.5;">`
            +   sections.join('')
            + `</td></tr>`;
        $row.after(subRow);
    }

    function addRowFromDiscogsData(discogsData, rowIdx, price, cond, sleeve) {
        return new Promise(function(resolve) {
            $.ajax({
                url: "{{ route('product.getMassProductRow') }}",
                type: 'GET',
                data: { index: rowIdx },
                success: function(rowHtml) {
                    const $row = $(rowHtml);
                    $('#product_rows_container').append($row);

                    // Sarah 2026-05-06: ONLY the title goes into Product Name
                    // and ONLY the artist goes into Artist — don't combine them
                    // into "Artist — Title" for the name input. Falls back to
                    // .name if title is missing for some reason.
                    const productTitle = discogsData.title || discogsData.name;
                    if (productTitle) {
                        $row.find('.product-name-autocomplete').val(productTitle);
                    }
                    if (discogsData.artist) {
                        $row.find('input[name*="[artist]"]').val(discogsData.artist);
                    }
                    // Carry the release's primary cover-art URL into the row's
                    // Image URL field. On save, massStore() downloads it into our
                    // uploads folder so the product view shows the photo.
                    if (discogsData.image_url) {
                        $row.find('input[name*="[image_url]"]').val(discogsData.image_url);
                    }
                    // Stash the Discogs release id so the saved product links
                    // back to its release (persisted by massStore()).
                    if (discogsData.discogs_release_id) {
                        $row.find('input[name*="[discogs_release_id]"]').val(discogsData.discogs_release_id);
                    }
                    if (price) {
                        $row.find('input[name*="[single_dsp_inc_tax]"]').val(price);
                    }
                    if (cond) $row.find('.massadd_condition').val(cond);
                    if (sleeve) $row.find('.massadd_sleeve_condition').val(sleeve);
                    // Pre-select Business Locations to the cashier's
                    // currently-open register (Hollywood / Pico / etc.) so
                    // bulk Discogs adds land in the store the operator is
                    // physically at without manual selection per row.
                    if (window.currentPosLocationId) {
                        const $locations = $row.find('.select2_business_locations');
                        const locId = String(window.currentPosLocationId);
                        if ($locations.find('option[value="' + locId + '"]').length) {
                            $locations.val([locId]).trigger('change');
                        }
                    }
                    // Pre-select category combo if Discogs gave us a match, then
                    // pre-fill Purchase Price with that category's default cost
                    // (used vinyl 0.35, sealed vinyl 17, used CD 0.10, …) so the
                    // operator can override but "Save & send to add purchase"
                    // always carries a cost. Only fills when the field is blank.
                    if (discogsData.category_id) {
                        const sub = discogsData.sub_category_id || 0;
                        const comboVal = discogsData.category_id + '_' + sub;
                        const $combo = $row.find('.category-combo-select');
                        const $opt = $combo.find('option[value="' + comboVal + '"]');
                        if ($opt.length) {
                            $combo.val(comboVal).trigger('change');
                            window.applyCategoryDefaultPurchasePrice($row);
                        }
                    }

                    // Sold-before badge: how many times this artist/title has
                    // sold at Pico vs. Hollywood vs. Discogs vs. Whatnot etc.
                    // Renders into the product-name cell beneath the name input.
                    // Guarded so a render failure never breaks the bulk queue.
                    try { renderSalesHistoryBadge($row, discogsData); }
                    catch (e) { console.warn('sales-history badge render failed', e); }

                    // Underpricing check vs Discogs for this grade (warms the cache for save).
                    if (price) {
                        setTimeout(function () {
                            const it = priceGuardItem($row);
                            if (it) runPriceGuard([it]).then(function (res) { if (res) markPriceGuard($row, res[it.idx]); });
                        }, 1500);
                    }

                    // Raise the typed price if our store consistently sells this
                    // release/title higher (same check as Bulk Product Entry).
                    if (price) {
                        const $price = $row.find('input[name*="[single_dsp_inc_tax]"]');
                        const catText = $.trim($row.find('.category-combo-select option:selected').text() || '');
                        lookupBulkPastSales([{
                            name: productTitle || '',
                            artist: discogsData.artist || '',
                            sku: discogsData.sku || '',
                            category: catText,
                            condition: cond || '',
                            price: price,
                            discogs_release_id: discogsData.discogs_release_id || '',
                        }]).then(function (results) {
                            const sp = results && results[0] && results[0].store_price;
                            // Skip if staff already changed the price meanwhile.
                            if (!sp || !sp.suggested || String($price.val()) !== String(price)) return;
                            const newPrice = Number(sp.suggested).toFixed(2);
                            $price.val(newPrice).trigger('change');
                            markStorePriceRaised($price, price, newPrice, storePriceNote(sp));
                            toastr.info('Raised ' + (productTitle || 'item') + ' from $' + price + ' to $' + newPrice + ': our store usually sells it for that.');
                        });
                    }

                    $row.find('.select2').select2();
                    if (typeof window.setupProductNameSelect2 === 'function') {
                        window.setupProductNameSelect2();
                    }
                    resolve($row);
                },
                error: function() { resolve(null); }
            });
        });
    }

    // Minimal CSV-line splitter that respects double-quoted fields. Used for the
    // direct-entry format `Product Name,Artist,Category,Subcategory,SKU,Price`.
    function parseCsvLine(line) {
        const out = [];
        let cur = '';
        let inQuotes = false;
        for (let i = 0; i < line.length; i++) {
            const ch = line[i];
            if (inQuotes) {
                if (ch === '"') {
                    if (line[i + 1] === '"') { cur += '"'; i++; }
                    else { inQuotes = false; }
                } else {
                    cur += ch;
                }
            } else if (ch === '"' && cur === '') {
                inQuotes = true;
            } else if (ch === ',') {
                out.push(cur);
                cur = '';
            } else {
                cur += ch;
            }
        }
        out.push(cur);
        return out.map(s => s.trim());
    }

    // Resolve a category/subcategory name pair to { category_id, sub_category_id }
    // by matching against the option labels in the global preset-bulk dropdown
    // (which mirrors flattenedProductCategoryCombos). Case-insensitive exact
    // match on "Category > Subcategory", or just "Category" when sub is blank.
    function resolveCategoryComboByName(categoryName, subCategoryName) {
        const cat = (categoryName || '').trim().toLowerCase();
        if (!cat) return null;
        const sub = (subCategoryName || '').trim().toLowerCase();
        const target = sub ? (cat + ' > ' + sub) : cat;
        let found = null;
        $('#preset_bulk_category option').each(function() {
            if (!$(this).val()) return; // skip placeholder
            const label = ($(this).text() || '').trim().toLowerCase();
            if (label === target) {
                found = {
                    category_id: parseInt($(this).attr('data-category-id'), 10) || null,
                    sub_category_id: parseInt($(this).attr('data-sub-category-id'), 10) || 0,
                };
                return false;
            }
        });
        return found;
    }

    // Parse one line into either a Discogs entry or a direct-CSV entry.
    // Discogs: bare numeric ID, or a URL with `/release/<id>`, optionally with
    //   a trailing price separated by a space — "1873085 19.99".
    // CSV: presence of a comma signals direct entry —
    //   "Product Name,Artist,Category,Subcategory,SKU,Price" (trailing fields optional).
    function parseDiscogsBulkLine(line) {
        const trimmed = (line || '').trim();
        if (!trimmed) return null;

        // CSV direct-entry path: any line containing a comma. Discogs URLs and
        // bare IDs never contain commas, so this is unambiguous.
        if (trimmed.indexOf(',') !== -1) {
            const fields = parseCsvLine(trimmed);
            const name = fields[0] || '';
            if (!name) return null;
            const priceRaw = (fields[5] || '').replace(/^\$/, '').trim();
            return {
                type: 'csv',
                csv: {
                    name: name,
                    artist: fields[1] || '',
                    category: fields[2] || '',
                    sub_category: fields[3] || '',
                    sku: fields[4] || '',
                    price: priceRaw || null,
                },
            };
        }

        // Discogs path. Price and condition can come in either order after
        // the ID/URL: "1873085 19.99 VG+", "1873085 VG+/G+ $19.99" (Sarah, 2026-10-26).
        // The slash is optional: "1873085 19.99 VG+ G+" reads as record VG+, cover G+.
        let head = trimmed;
        let price = null;
        let cond = null;
        let sleeve = null;
        const gradeWords = []; // space-separated grades, in typed order
        for (let guard = 0; guard < 5; guard++) {
            const priceMatch = head.match(/\s+\$?(\d+(?:\.\d+)?)\s*$/);
            const condMatch = head.match(/\s+([A-Za-z+\-]+(?:\s*\/\s*[A-Za-z+\-]+)?)\s*$/);
            const word = condMatch && condMatch[1].indexOf('/') === -1 ? condMatch[1] : null;
            if (priceMatch && price === null) {
                price = priceMatch[1];
                head = head.slice(0, priceMatch.index).trim();
            } else if (condMatch && !word && cond === null && !gradeWords.length && parseGrade(condMatch[1].split('/')[0])) {
                const parts = condMatch[1].split('/');
                cond = parseGrade(parts[0]);
                sleeve = parts[1] ? parseGrade(parts[1], true) : null;
                head = head.slice(0, condMatch.index).trim();
            } else if (word && cond === null && gradeWords.length < 2 && (parseGrade(word) || parseGrade(word, true))) {
                gradeWords.unshift(word);
                head = head.slice(0, condMatch.index).trim();
            } else break;
        }
        if (gradeWords.length) {
            cond = parseGrade(gradeWords[0]);
            sleeve = gradeWords[1] ? parseGrade(gradeWords[1], true) : (cond ? null : parseGrade(gradeWords[0], true));
        }
        let id = null;
        if (/^\d+$/.test(head)) {
            id = head;
        } else {
            const m = head.match(/\/release\/(\d+)/i);
            if (m) id = m[1];
        }
        if (!id) return null;
        return { type: 'discogs', id: id, price: price, cond: cond, sleeve: sleeve };
    }

    // Short grade -> the Discogs wording the website shows.
    function parseGrade(raw, isSleeve) {
        const k = String(raw || '').trim().toUpperCase().replace(/\s+/g, '');
        const map = {
            'SEALED': 'Sealed', 'S': 'Sealed', 'SS': 'Sealed',
            'M': 'Mint (M)', 'MINT': 'Mint (M)',
            'NM': 'Near Mint (NM or M-)', 'M-': 'Near Mint (NM or M-)', 'NM-': 'Near Mint (NM or M-)',
            'VG+': 'Very Good Plus (VG+)', 'VG': 'Very Good (VG)',
            'G+': 'Good Plus (G+)', 'G': 'Good (G)', 'F': 'Fair (F)', 'P': 'Poor (P)',
        };
        if (map[k]) return map[k];
        if (isSleeve && (k === 'GENERIC' || k === 'GEN')) return 'Generic';
        if (isSleeve && (k === 'NOCOVER' || k === 'NC')) return 'No Cover';
        return null;
    }

    // Show how each typed line will be read, and nudge for a missing price or condition.
    $('#bulk_discogs_ids').on('input', function() {
        const lines = ($(this).val() || '').split(/\r?\n/).filter(l => l.trim());
        const esc = t => $('<div>').text(t).html();
        const html = lines.slice(0, 50).map(function(l) {
            const e = parseDiscogsBulkLine(l);
            if (!e) return `<div style="color:#b91c1c;font-weight:700;">${esc(l.trim())}: can't read this line. Start with the Discogs number.</div>`;
            if (e.type === 'csv') return `<div>${esc(e.csv.name)}: added as typed (not from Discogs)</div>`;
            const bits = [`Discogs ${e.id}`, e.price ? `$${e.price}` : '<span style="color:#b91c1c;font-weight:700;">no price</span>',
                e.cond ? `Media ${esc(e.cond)}` : '<span style="color:#b91c1c;font-weight:700;">add a grade (VG+ VG+, G VG+...)</span>'];
            if (e.sleeve) bits.push(`Sleeve ${esc(e.sleeve)}`);
            return `<div>${bits.join(', ')}</div>`;
        }).join('');
        $('#bulk_discogs_preview').html(html);
    });

    $('#fetch_discogs_ids').on('click', function() {
        const raw = $('#bulk_discogs_ids').val() || '';
        const entries = raw.split(/\r?\n/)
                           .map(parseDiscogsBulkLine)
                           .filter(Boolean);
        if (!entries.length) {
            toastr.warning('Paste at least one Discogs release ID, release URL, or CSV row.');
            return;
        }
        const discogsCount = entries.filter(e => e.type === 'discogs').length;
        const csvCount = entries.length - discogsCount;

        // Nudge for grades before fetching: without them the price label
        // prints no condition. "Add anyway" re-clicks with the nudge skipped.
        const ungraded = entries.filter(e => e.type === 'discogs' && !e.cond).length;
        const $fetchBtn = $(this);
        if (ungraded && !$fetchBtn.data('skipGradeNudge')) {
            swal({
                title: 'Add the grades?',
                content: {
                    element: 'div',
                    attributes: {
                        innerHTML: `<div style="text-align:left;font-size:15px;line-height:1.5;">`
                            + `${ungraded === discogsCount ? 'None' : ungraded + ' of ' + discogsCount} of your lines ${ungraded === discogsCount ? 'have' : 'are missing'} a Media/Sleeve grade.<br><br>`
                            + `It helps to add them after the price so the grade prints on the label. Media grade first, then sleeve:<br><br>`
                            + `<div style="font-family:monospace;font-size:15px;background:#f5f5f5;padding:8px 10px;border-radius:4px;">`
                            + `<div style="display:flex;align-items:flex-end;gap:6px;flex-wrap:wrap;">`
                            + `<span>https://www.discogs.com/release/1873085 19.99</span>`
                            + `<span style="display:inline-flex;flex-direction:column;align-items:center;"><small style="font-family:sans-serif;font-weight:700;color:#1d4ed8;">Media</small><b style="color:#1d4ed8;">VG+</b></span>`
                            + `<span style="display:inline-flex;flex-direction:column;align-items:center;"><small style="font-family:sans-serif;font-weight:700;color:#b45309;">Sleeve</small><b style="color:#b45309;">VG+</b></span>`
                            + `</div></div><br>`
                            + `More examples:<br>`
                            + `<code>https://www.discogs.com/release/1873085 19.99 VG+ VG+</code><br>`
                            + `<code>https://www.discogs.com/release/1873085 12.99 G VG+</code><br>`
                            + `<code>https://www.discogs.com/release/249504 24.99 NM VG</code><br>`
                            + `<code>https://www.discogs.com/release/1873085 9.99 VG+ Generic</code><br><br>`
                            + `<span style="color:#1d4ed8;font-weight:700;">Media</span> = the vinyl/CD/tape itself. <span style="color:#b45309;font-weight:700;">Sleeve</span> = the cover or case.</div>`
                    }
                },
                icon: 'info',
                buttons: {
                    cancel: { text: 'Go back & add grades', visible: true, value: false },
                    confirm: { text: 'Add without grades', value: true, className: 'btn btn-default' }
                }
            }).then(function (go) {
                if (go) {
                    $fetchBtn.data('skipGradeNudge', true).trigger('click');
                } else {
                    $('#bulk_discogs_ids').focus();
                }
            });
            return;
        }
        $fetchBtn.removeData('skipGradeNudge');
        const promptParts = [];
        if (discogsCount) promptParts.push(`fetch ${discogsCount} from Discogs`);
        if (csvCount) promptParts.push(`add ${csvCount} CSV row${csvCount === 1 ? '' : 's'}`);
        if (!confirm(`About to ${promptParts.join(' and ')}. Continue?`)) {
            return;
        }
        removeBlankMassRows();

        // Rows are appended to the bottom, so iterate in typed order
        // (first entry → highest existing row, last entry → very bottom).
        const queue = entries.slice();
        const $btn = $(this).prop('disabled', true);
        const total = entries.length;
        let added = 0, failed = 0;
        const failures = [];
        const warnings = [];

        // Discogs 429 recovery. Verified 2026-07-08: once the ~60/min limit is
        // tripped, Discogs keeps returning 429 for roughly a full minute and
        // sends no Retry-After. So a rate-limited row can't be salvaged in the
        // moment — instead we collect the 429'd entries, wait out the window,
        // and retry them. Prevention (the ~1.1s spacing below) keeps a normal
        // single batch under the limit; this is the safety net for when it
        // still trips (e.g. a sync sharing the token).
        let retryQueue = [];
        let rateRound = 0;
        const RATE_LIMIT_MAX_ROUNDS = 2;
        const RATE_LIMIT_WAIT_MS = 65000;

        function isRateLimited(msg, status) {
            if (status === 429) return true;
            msg = msg || '';
            return /\b429\b/.test(msg) || /rate limit/i.test(msg);
        }

        function renderList(title, items, cls) {
            if (!items.length) return '';
            const lis = items.map(f =>
                `<li><code>${f.id}</code> &mdash; ${$('<div>').text(f.msg).html()}</li>`
            ).join('');
            return `<div class="alert ${cls}" style="margin-top:10px;">
                <strong>${title} (${items.length}):</strong>
                <ul style="margin:6px 0 0 18px;">${lis}</ul>
            </div>`;
        }

        function finish() {
            $btn.prop('disabled', false);

            // Drop the always-present empty starter row(s) so only the
            // fetched rows remain. A row counts as blank when its Product
            // Name is empty; never remove the last remaining row.
            if (added > 0) {
                $('#product_rows_container .product-row').each(function() {
                    const $r = $(this);
                    const name = ($r.find('.product-name-autocomplete').val() || '').trim();
                    if (!name && $('#product_rows_container .product-row').length > 1) {
                        const idx = $r.attr('data-row-index');
                        if (idx !== undefined) {
                            $(`tr.discogs-sold-before-row[data-row-index="${idx}"]`).remove();
                        }
                        $r.remove();
                    }
                });

                // Jump to the last (most recently added) row.
                const $last = $('#product_rows_container .product-row').last();
                if ($last.length) {
                    $last[0].scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
            }

            const bits = [`Added ${added}/${total}`];
            if (failed) bits.push(`${failed} failed`);
            if (warnings.length) bits.push(`${warnings.length} with warnings`);
            const summary = bits.join(' &mdash; ') + '.';
            const cls = failed ? 'text-warning' : (warnings.length ? 'text-warning' : 'text-success');
            const icon = failed ? 'exclamation-triangle' : (warnings.length ? 'exclamation-circle' : 'check');
            $('#discogs_fetch_status').html(
                `<span class="${cls}"><i class="fa fa-${icon}"></i> ${summary}</span>` +
                renderList('Failed', failures, 'alert-warning') +
                renderList('Added with warnings', warnings, 'alert-info')
            );
            if (!failed && !warnings.length) {
                toastr.success(`Added ${added} row${added === 1 ? '' : 's'}.`);
                $('#bulk_discogs_ids').val(''); $('#bulk_discogs_preview').empty();
            } else if (failed) {
                toastr.warning(`${failed} of ${total} entries failed. See details on the page.`);
            } else {
                toastr.warning(`${warnings.length} row${warnings.length === 1 ? '' : 's'} added with warnings. See details on the page.`);
            }
        }

        function handleCsv(entry) {
            const csv = entry.csv;
            let combo = resolveCategoryComboByName(csv.category, csv.sub_category);
            if (!combo && csv.category && typeof window.massAddCategoryMatch === 'function') {
                const $opts = $('#preset_bulk_category option');
                const val = window.massAddCategoryMatch($opts.map(function() {
                    return { value: String($(this).val() || ''), label: $.trim($(this).text()) };
                }).get(), { category: csv.category, subcategory: csv.sub_category, name: csv.name, artist: csv.artist });
                const $hit = val ? $opts.filter(function() { return String($(this).val()) === val; }).first() : $();
                if ($hit.length) {
                    combo = {
                        category_id: parseInt($hit.attr('data-category-id'), 10) || null,
                        sub_category_id: parseInt($hit.attr('data-sub-category-id'), 10) || 0,
                    };
                }
            }
            if (csv.category && !combo) {
                warnings.push({
                    id: csv.name,
                    msg: `Category "${csv.category}${csv.sub_category ? ' > ' + csv.sub_category : ''}" did not match any existing combo — row added without category.`,
                });
            }
            const data = {
                title: csv.name,
                artist: csv.artist,
                sku: csv.sku,
                category_id: combo ? combo.category_id : null,
                sub_category_id: combo ? combo.sub_category_id : null,
            };
            return addRowFromDiscogsData(data, nextMassRowIndex(), csv.price)
                .then(() => { added++; });
        }

        // Resolves with true when the row was rate-limited (429) and should be
        // re-queued for a later round; false when it's done (added or hard-failed).
        function handleDiscogs(entry) {
            return new Promise(function(resolve) {
                const id = entry.id;
                const price = entry.price;
                $('#discogs_fetch_status').html(`<i class="fa fa-spinner fa-spin"></i> Fetching ${id} (${added + failed + 1}/${total})...`);
                $.ajax({
                    url: "{{ url('product/mass-create/fetch-discogs-release') }}/" + encodeURIComponent(id),
                    type: 'GET',
                    success: function(resp) {
                        if (resp && resp.success) {
                            addRowFromDiscogsData(resp.data, nextMassRowIndex(), price, entry.cond, entry.sleeve)
                                .then(() => { added++; resolve(false); });
                        } else {
                            const msg = (resp && resp.message) ? resp.message : 'Unknown error from server.';
                            if (isRateLimited(msg, 0)) { resolve(true); return; }
                            failed++;
                            failures.push({ id: id, msg: msg });
                            console.warn('Discogs fetch failed for ' + id + ':', msg);
                            resolve(false);
                        }
                    },
                    error: function(xhr) {
                        const status = (xhr && xhr.status) ? xhr.status : 0;
                        if (isRateLimited('', status)) { resolve(true); return; }
                        failed++;
                        let msg = 'HTTP ' + (status || '?');
                        if (xhr && xhr.responseJSON && xhr.responseJSON.message) {
                            msg += ' &mdash; ' + xhr.responseJSON.message;
                        } else if (xhr && xhr.responseText) {
                            msg += ' &mdash; ' + xhr.responseText.substring(0, 200).replace(/<[^>]+>/g, ' ').trim();
                        }
                        failures.push({ id: id, msg: msg });
                        console.warn('Discogs fetch HTTP error for ' + id, xhr);
                        resolve(false);
                    }
                });
            });
        }

        function next() {
            if (!queue.length) {
                // Main pass drained. If Discogs throttled some rows, wait out the
                // ~60s window and retry them rather than reporting them failed.
                if (retryQueue.length && rateRound < RATE_LIMIT_MAX_ROUNDS) {
                    rateRound++;
                    const batch = retryQueue;
                    retryQueue = [];
                    const secs = Math.round(RATE_LIMIT_WAIT_MS / 1000);
                    $('#discogs_fetch_status').html(
                        `<span class="text-warning"><i class="fa fa-clock-o"></i> ` +
                        `Discogs rate-limited ${batch.length} row${batch.length === 1 ? '' : 's'} — ` +
                        `waiting ${secs}s for the limit to reset, then retrying (round ${rateRound}/${RATE_LIMIT_MAX_ROUNDS})...</span>`
                    );
                    setTimeout(function() {
                        queue.push.apply(queue, batch);
                        next();
                    }, RATE_LIMIT_WAIT_MS);
                    return;
                }
                // Out of rounds — anything still rate-limited is a real failure.
                if (retryQueue.length) {
                    retryQueue.forEach(function(e) {
                        failed++;
                        failures.push({ id: e.id, msg: 'Discogs rate limit (429) — still throttled after ' + RATE_LIMIT_MAX_ROUNDS + ' retry rounds.' });
                    });
                    retryQueue = [];
                }
                finish();
                return;
            }
            const entry = queue.shift();
            if (entry.type === 'csv') {
                $('#discogs_fetch_status').html(`<i class="fa fa-spinner fa-spin"></i> Adding CSV row (${added + failed + 1}/${total})...`);
                handleCsv(entry).then(() => setTimeout(next, 50));
            } else {
                // Discogs allows ~60 lookups/min; space requests ~1.1s apart so
                // a normal paste stays under the limit instead of tripping 429.
                handleDiscogs(entry).then(function(rateLimited) {
                    if (rateLimited) { retryQueue.push(entry); }
                    setTimeout(next, 1100);
                });
            }
        }
        next();
    });

    $('#clear_discogs_ids').on('click', function() {
        $('#bulk_discogs_ids').val(''); $('#bulk_discogs_preview').empty();
        $('#discogs_fetch_status').html('');
    });

    // ============================================================
    //  Sarah 2026-05-06: Preset Category bulk entry
    //  Pick a category once, paste names, every new row uses that category.
    // ============================================================
    // Sarah 2026-05-06: parsed columns intentionally narrowed to match the
    // Bulk Discogs IDs flow — Name, Artist, SKU, plus the preset category.
    // Price/Bin/Location are filled by hand in the row, same as Discogs.
    function parsePresetBulkLine(line) {
        const trimmed = line.trim();
        if (!trimmed) return null;

        let parts = null;
        if (trimmed.indexOf('|') !== -1) {
            parts = trimmed.split('|').map(s => s.trim());
        } else if (trimmed.indexOf('\t') !== -1) {
            parts = trimmed.split('\t').map(s => s.trim());
        } else if (trimmed.indexOf(',') !== -1) {
            parts = trimmed.split(',').map(s => s.trim());
        }
        if (parts && parts.length > 1) {
            return {
                name: parts[0] || '',
                artist: parts[1] || '',
                sku: parts[2] || '',
            };
        }
        const dashIdx = trimmed.indexOf(' - ');
        if (dashIdx !== -1) {
            return {
                name: trimmed.slice(0, dashIdx).trim(),
                artist: trimmed.slice(dashIdx + 3).trim(),
            };
        }
        return { name: trimmed };
    }

    function addRowFromPresetData(productData, rowIdx, comboVal) {
        return new Promise(function(resolve) {
            $.ajax({
                url: "{{ route('product.getMassProductRow') }}",
                type: 'GET',
                data: { index: rowIdx },
                success: function(rowHtml) {
                    const $row = $(rowHtml);
                    $('#product_rows_container').prepend($row);
                    // Same column set as the Bulk Discogs flow: Name, Artist, SKU.
                    if (productData.name)   $row.find('.product-name-autocomplete').val(productData.name);
                    if (productData.artist) $row.find('input[name*="[artist]"]').val(productData.artist);
                    if (productData.sku)    $row.find('input[name*="[sku]"]').val(productData.sku);

                    // Apply the preset category combo.
                    if (comboVal) {
                        const $combo = $row.find('.category-combo-select');
                        if ($combo.find('option[value="' + comboVal + '"]').length) {
                            $combo.val(comboVal).trigger('change');
                        }
                    }
                    $row.find('.select2').select2();
                    if (typeof window.setupProductNameSelect2 === 'function') {
                        window.setupProductNameSelect2();
                    }
                    resolve($row);
                },
                error: function() { resolve(null); }
            });
        });
    }

    $('#add_preset_bulk').on('click', function() {
        const comboVal = $('#preset_bulk_category').val();
        if (!comboVal) {
            toastr.warning('Pick a category first.');
            return;
        }
        const raw = $('#preset_bulk_text').val() || '';
        const products = raw.split(/\r?\n/).map(parsePresetBulkLine).filter(Boolean);
        if (!products.length) {
            toastr.warning('Paste at least one product line.');
            return;
        }
        if (!confirm(`Add ${products.length} rows with the selected category?`)) {
            return;
        }
        removeBlankMassRows();

        // Reverse so first-typed product ends up at the top after prepending.
        const queue = products.slice().reverse();
        const $btn = $(this).prop('disabled', true);
        const total = products.length;
        let added = 0;

        function next() {
            if (!queue.length) {
                $btn.prop('disabled', false);
                const msg = `Added ${added}/${total} preset-category rows.`;
                $('#preset_bulk_status').html(`<span class="text-success"><i class="fa fa-check"></i> ${msg}</span>`);
                toastr.success(msg);
                $('#preset_bulk_text').val('');
                return;
            }
            const p = queue.shift();
            $('#preset_bulk_status').html(`<i class="fa fa-spinner fa-spin"></i> Adding ${added + 1}/${total}...`);
            addRowFromPresetData(p, nextMassRowIndex(), comboVal)
                .then(() => { added++; setTimeout(next, 150); });
        }
        next();
    });

    $('#clear_preset_bulk').on('click', function() {
        $('#preset_bulk_text').val('');
        $('#preset_bulk_status').html('');
    });
    
    // Enhanced autocomplete for bulk text area (suggestions from existing products)
    let autocompleteCache = {};
    $('#bulk_product_text').on('keydown', function(e) {
        // Trigger autocomplete on Tab or when typing product names
        if (e.key === 'Tab' || e.key === 'Enter') {
            const cursorPos = this.selectionStart;
            const textBefore = this.value.substring(0, cursorPos);
            const lines = textBefore.split('\n');
            const currentLine = lines[lines.length - 1];
            const words = currentLine.split(/\s+/);
            const lastWord = words[words.length - 1] || '';
            
            // If last word looks like a product name (2+ chars), try to autocomplete
            if (lastWord.length >= 2 && !lastWord.includes('|') && !lastWord.includes(',')) {
                // This is a simple implementation - could be enhanced with actual API call
                // For now, we'll rely on the preview feature
            }
        }
    });
</script>

<script>
// Buy record # on Mass Add. Loading a buy adds a "From buy lot" dropdown to
// every row (existing and new — any of the add paths). Picking a lot fills the
// row's Purchase Price with what we paid per unit for that lot; new rows
// default to the last lot used, so a box of one genre needs no clicks per row.
// On save, massStore() tags each new product with the buy + lot.
(function () {
    var lookupUrl = "{{ route('product.massCreate.buyLookup') }}";
    var fetchUrl = "{{ url('product/mass-create/fetch-discogs-release') }}/";
    var buy = null;
    var lastLot = '';

    function money(n) { return '$' + Number(n || 0).toFixed(2); }
    function esc(t) { return $('<div>').text(t == null ? '' : String(t)).html(); }
    function lotById(id) {
        if (!buy) return null;
        for (var i = 0; i < buy.lots.length; i++) {
            if (String(buy.lots[i].id) === String(id)) return buy.lots[i];
        }
        return null;
    }
    function lotName(l) {
        return l.title ? (l.individual ? l.title : l.type + ' — ' + l.title) : l.type;
    }
    // Compact label for the narrow row dropdown: drop the "10+ Bulk Vinyl: "
    // style prefix and add the grade, so same-genre lots at different grades
    // stay distinguishable ("Rock/Alt/Reggae/Electronic VG+ · $0.86").
    function lotShortName(l) {
        var base = (l.individual && l.title) ? l.title : l.type.replace(/^[^:]*:\s*/, '');
        if (!l.individual && l.title) base += ' — ' + l.title;
        return base + (l.grade ? ' ' + l.grade : '');
    }

    function setRowCost($row, lot) {
        if (!lot) return;
        $row.find('input[name*="[single_dpp_inc_tax]"]').first().val(Number(lot.unit_cost).toFixed(4));
    }

    // fresh = row was just added (its cost is only a category default, safe to
    // replace); existing rows keep any cost already typed.
    function decorateRow($row, fresh) {
        if (!buy || !$row.hasClass('product-row') || $row.find('.bfc-lot-select').length) return;
        var idx = $row.attr('data-row-index');
        var html = '<select name="products[' + esc(idx) + '][buy_offer_line_id]" class="form-control input-sm bfc-lot-select" style="margin-top:4px; min-width:170px;" title="Which lot of the buy this item came from">'
            + '<option value="">Not from this buy</option>';
        buy.lots.forEach(function (l) {
            html += '<option value="' + l.id + '">' + esc(lotShortName(l)) + ' · ' + money(l.unit_cost) + '</option>';
        });
        html += '</select>';
        var $sel = $(html);
        $row.find('td.price-col').first().append($sel);
        if (lastLot) {
            $sel.val(lastLot);
            var $pp = $row.find('input[name*="[single_dpp_inc_tax]"]').first();
            var cur = parseFloat(($pp.val() || '').toString().replace(/,/g, ''));
            if (fresh || isNaN(cur) || cur <= 0) setRowCost($row, lotById(lastLot));
        }
    }

    $(document).on('change', '#product_rows_container .bfc-lot-select', function () {
        lastLot = $(this).val();
        setRowCost($(this).closest('.product-row'), lotById(lastLot));
    });

    var container = document.getElementById('product_rows_container');
    if (container && window.MutationObserver) {
        new MutationObserver(function (muts) {
            muts.forEach(function (m) {
                Array.prototype.forEach.call(m.addedNodes, function (n) {
                    if (n.nodeType === 1) decorateRow($(n), true);
                });
            });
        }).observe(container, { childList: true });
    }

    function renderSummary() {
        var o = buy.offer;
        var html = '<div class="alert alert-info" style="margin:0;">'
            + '<strong>' + esc(o.number) + '</strong>'
            + (o.seller ? ' · ' + esc(o.seller) : '')
            + (o.accepted_at ? ' · bought ' + esc(o.accepted_at) : '')
            + (o.store ? ' at ' + esc(o.store) : '')
            + '. New rows are tagged with this buy; pick the lot on each row (it remembers the last one).'
            + '<table class="table table-condensed" style="margin:8px 0 0; background:transparent;"><thead><tr>'
            + '<th>Lot</th><th class="text-right">Paid / unit</th><th class="text-right">Listed so far</th></tr></thead><tbody>';
        var individual = 0;
        buy.lots.forEach(function (l) {
            html += '<tr><td>' + esc(lotName(l)) + (l.grade ? ' <small class="text-muted">' + esc(l.grade) + '</small>' : '') + '</td>'
                + '<td class="text-right">' + money(l.unit_cost) + '</td>'
                + '<td class="text-right">' + l.listed + ' of ' + Number(l.qty) + '</td></tr>';
            if (l.individual && (l.title || l.discogs_release_id) && l.listed < l.qty) individual++;
        });
        html += '</tbody></table>';
        if (individual) {
            html += '<button type="button" id="bfc_add_individual" class="btn btn-primary btn-sm" style="margin-top:6px;">'
                + '<i class="fa fa-plus"></i> Add the ' + individual + ' individually entered record' + (individual === 1 ? '' : 's') + ' as rows</button>'
                + ' <span id="bfc_add_status" class="text-muted small"></span>';
        }
        html += '</div>';
        $('#bfc_buy_summary').html(html).show();
    }

    $('#bfc_buy_load').on('click', function () {
        var val = $.trim($('#bfc_buy_number').val());
        if (!val) return;
        var $btn = $(this).prop('disabled', true);
        $.getJSON(lookupUrl, { buy: val }).done(function (resp) {
            if (!resp || !resp.success) {
                toastr.error((resp && resp.msg) || 'Could not load that buy.');
                return;
            }
            clearBuy();
            buy = resp;
            // Default new rows to the first bulk lot (most rows in a big box).
            var firstBulk = buy.lots.filter(function (l) { return !l.individual; })[0];
            lastLot = firstBulk ? String(firstBulk.id) : '';
            $('#bfc_buy_offer_id').val(buy.offer.id);
            $('#bfc_buy_number').val(buy.offer.number).prop('readonly', true);
            $('#bfc_buy_clear').show();
            renderSummary();
            $('#product_rows_container .product-row').each(function () { decorateRow($(this), false); });
        }).fail(function () {
            toastr.error('Could not load that buy.');
        }).always(function () { $btn.prop('disabled', false); });
    });
    $('#bfc_buy_number').on('keydown', function (e) {
        if (e.key === 'Enter') { e.preventDefault(); $('#bfc_buy_load').click(); }
    });

    function clearBuy() {
        buy = null;
        lastLot = '';
        $('#bfc_buy_offer_id').val('');
        $('#bfc_buy_number').prop('readonly', false);
        $('#bfc_buy_clear').hide();
        $('#bfc_buy_summary').hide().empty();
        $('#product_rows_container .bfc-lot-select').remove();
    }
    $('#bfc_buy_clear').on('click', function () { clearBuy(); $('#bfc_buy_number').val('').focus(); });

    // One row per not-yet-listed copy of each individually entered record,
    // through the same row builder the bulk Discogs add uses. Sequential to
    // stay under Discogs' rate limit.
    $(document).on('click', '#bfc_add_individual', function () {
        var $btn = $(this).prop('disabled', true);
        var queue = [];
        buy.lots.forEach(function (l) {
            if (!l.individual || !(l.title || l.discogs_release_id)) return;
            for (var i = l.listed; i < l.qty; i++) queue.push(l);
        });
        var done = 0;
        function next() {
            if (!queue.length) {
                $('#bfc_add_status').text('Added ' + done + ' row' + (done === 1 ? '' : 's') + '. Check each one and set the selling price.');
                return;
            }
            var l = queue.shift();
            var idx = nextMassRowIndex();
            $('#bfc_add_status').text('Adding ' + (done + 1) + '…');
            var addRow = function (data) {
                return addRowFromDiscogsData(data, idx, '').then(function () {
                    var $row = $('#product_rows_container .product-row[data-row-index="' + idx + '"]');
                    $row.find('.bfc-lot-select').val(String(l.id));
                    setRowCost($row, l);
                    done++;
                    next();
                });
            };
            if (l.discogs_release_id) {
                $.getJSON(fetchUrl + encodeURIComponent(l.discogs_release_id)).done(function (resp) {
                    addRow(resp && resp.success ? resp.data : { title: l.title });
                }).fail(function () { addRow({ title: l.title }); });
            } else {
                addRow({ title: l.title });
            }
        }
        next();
    });
})();
</script>
@endsection
