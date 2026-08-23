<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, noarchive">
    <title>{{ $title }} · {{ config('app.name') }}</title>
    <style>
        :root {
            color-scheme: light;
            font-family: Arial, Helvetica, sans-serif;
            color: #111827;
            background: #fff;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            background: #eef0f2;
        }

        .print-toolbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            max-width: 1000px;
            margin: 16px auto;
            padding: 14px 18px;
            border: 1px solid #cbd2d9;
            background: #fff;
        }

        .print-toolbar p {
            margin: 3px 0 0;
            color: #4b5563;
            font-size: 14px;
        }

        .print-toolbar-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }

        .print-toolbar a,
        .print-toolbar button {
            display: inline-flex;
            align-items: center;
            min-height: 44px;
            padding: 10px 18px;
            border: 1px solid #0a2036;
            border-radius: 6px;
            color: #0a2036;
            background: #fff;
            font: inherit;
            font-weight: 700;
            cursor: pointer;
            text-decoration: none;
        }

        .print-toolbar button {
            color: #fff;
            background: #0a2036;
        }

        .print-document {
            width: 210mm;
            min-height: 297mm;
            margin: 0 auto 24px;
            padding: 12mm;
            background: #fff;
        }

        .print-heading {
            margin: 0 0 7mm;
            padding-bottom: 4mm;
            border-bottom: .35mm solid #111827;
        }

        .print-heading h1 {
            margin: 0 0 1.5mm;
            font-size: 18pt;
            line-height: 1.15;
        }

        .print-heading p {
            margin: 0;
            color: #374151;
            font-size: 10pt;
        }

        .qr-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 7mm;
        }

        .qr-card {
            display: flex;
            min-width: 0;
            height: 119mm;
            break-inside: avoid;
            page-break-inside: avoid;
            flex-direction: column;
            align-items: center;
            justify-content: flex-start;
            padding: 7mm;
            border: .35mm solid #4b5563;
            text-align: center;
        }

        .qr-card img {
            display: block;
            width: 55mm;
            height: 55mm;
            flex: 0 0 55mm;
            margin: 0 auto 4mm;
            background: #fff;
        }

        .qr-card h2 {
            max-width: 100%;
            margin: 0 0 2mm;
            overflow-wrap: anywhere;
            font-family: Georgia, "Times New Roman", serif;
            font-size: 14pt;
            line-height: 1.12;
        }

        .qr-card .program {
            max-width: 100%;
            margin: 0 0 2.5mm;
            overflow-wrap: anywhere;
            font-size: 9.5pt;
            line-height: 1.25;
        }

        .qr-card dl {
            display: grid;
            width: 100%;
            grid-template-columns: auto 1fr;
            gap: 1mm 3mm;
            margin: auto 0 0;
            padding-top: 3mm;
            border-top: .25mm solid #9ca3af;
            font-size: 8.5pt;
            text-align: left;
        }

        .qr-card dt {
            color: #4b5563;
        }

        .qr-card dd {
            min-width: 0;
            margin: 0;
            overflow-wrap: anywhere;
            font-weight: 700;
        }

        .individual .qr-grid {
            display: flex;
            justify-content: center;
        }

        .individual .qr-card {
            width: 100mm;
            height: 145mm;
            padding: 9mm;
        }

        .individual .qr-card img {
            width: 65mm;
            height: 65mm;
            flex-basis: 65mm;
        }

        .individual .qr-card h2 {
            font-size: 18pt;
        }

        @page {
            size: A4 portrait;
            margin: 12mm;
        }

        @media print {
            body {
                background: #fff;
            }

            .print-toolbar {
                display: none !important;
            }

            .print-document {
                width: auto;
                min-height: 0;
                margin: 0;
                padding: 0;
            }

            .print-heading {
                margin-bottom: 6mm;
            }

            .qr-grid {
                gap: 7mm;
            }
        }
    </style>
</head>
<body>
    <aside class="print-toolbar" aria-label="Print guidance">
        <div>
            <strong>{{ $title }}</strong>
            <p>A4 portrait · 100% scale · disable Fit to page and browser headers/footers.</p>
        </div>
        <div class="print-toolbar-actions">
            <a href="{{ $backUrl }}">Back to QR preparation</a>
            <button type="button" onclick="window.print()">Open print dialog</button>
        </div>
    </aside>

    <main class="print-document {{ $mode }}">
        <header class="print-heading">
            <h1>{{ $ceremony->name }}</h1>
            <p>
                {{ $title }} · {{ count($cards) }} {{ count($cards) === 1 ? 'graduate' : 'graduates' }}
                · Generated {{ $generatedAt->format('M j, Y g:i A') }}
            </p>
        </header>

        <section class="qr-grid" aria-label="Graduate QR cards">
            @foreach ($cards as $card)
                <article class="qr-card">
                    <img
                        src="{{ $card['qr_data_uri'] }}"
                        alt="QR code for {{ $card['display_name'] }}"
                        width="512"
                        height="512"
                    >
                    <h2>{{ $card['display_name'] }}</h2>
                    <p class="program">{{ $card['program'] }}</p>
                    <dl>
                        <dt>Sequence</dt>
                        <dd>{{ $card['sequence_number'] }}</dd>
                        <dt>Student number</dt>
                        <dd>{{ $card['student_number'] }}</dd>
                    </dl>
                </article>
            @endforeach
        </section>
    </main>
</body>
</html>
