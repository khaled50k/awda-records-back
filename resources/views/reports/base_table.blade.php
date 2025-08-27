<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <title>{{ $title }}</title>
    <style>
        @page { size: A4 portrait; margin: 18mm; }

        body { 
            font-family: "Cairo", "DejaVu Sans", "Amiri", sans-serif; 
            direction: rtl; 
            unicode-bidi: bidi-override; 
            color: #111; 
            line-height: 1.9; 
            font-size: 13px;
            background-color: #fff;
        }

        table { 
            width: 100%; 
            border-collapse: separate; 
            border-spacing: 0; 
            table-layout: fixed;
            margin-top: 8px;
        }

        thead { display: table-header-group; }
        tfoot { display: table-row-group; }
        tr { page-break-inside: avoid; }

        th, td { 
            border: 1px solid #d1d5db; 
            padding: 10px 12px; 
            text-align: right; 
            font-size: 12.5px; 
            word-wrap: break-word; 
            white-space: pre-wrap; 
            word-break: break-word; 
            hyphens: auto;
        }

        thead th { 
            background-color: #f1f5f9; 
            color: #111; 
            font-weight: 600; 
            font-size: 13px;
        }

        tbody tr:nth-child(even) td { 
            background-color: #fafafa; 
        }

        tbody tr:hover td {
            background-color: #f3f4f6;
        }

        /* Make columns more balanced */
        tbody td:first-child, thead th:first-child { width: 20%; }
        tbody td:nth-child(2), thead th:nth-child(2) { width: 18%; }
        tbody td, thead th { width: auto; }

        /* Header cells */
        .title-cell { 
            background-color: #e2e8f0; 
            font-weight: bold; 
            font-size: 17px; 
            text-align: center; 
            padding: 14px; 
            border-radius: 6px 6px 0 0;
        }

        .subtitle-cell { 
            background-color: #f9fafb; 
            font-size: 12px; 
            text-align: center; 
            color: #374151; 
            padding: 8px; 
            border-bottom: 2px solid #e5e7eb;
        }

        /* Footer/summary */
        .summary { 
            margin-top: 14px; 
            padding: 12px 14px; 
            background-color: #f9fafb; 
            border: 1px solid #e5e7eb; 
            border-radius: 6px; 
            font-size: 12.5px; 
            line-height: 1.7; 
            color: #333;
        }

    </style>
</head>
<body>
    @php $colspan = max(1, count($headers)); @endphp

    <table>
        <thead>
            <tr>
                <th class="title-cell" colspan="{{ $colspan }}">{{ $title }}</th>
            </tr>
            <tr>
                <th class="subtitle-cell" colspan="{{ $colspan }}">
                    نظام السجلات الطبية - AWDA &nbsp;&nbsp;|&nbsp;&nbsp; تقرير بتاريخ: {{ $generatedAt }}
                    @if(isset($meta['date_range']))
                        &nbsp;&nbsp;|&nbsp;&nbsp; الفترة: من {{ $meta['date_range']['from_date'] ?? '' }} إلى {{ $meta['date_range']['to_date'] ?? '' }}
                    @endif
                </th>
            </tr>
            <tr>
                @foreach($headers as $header)
                    <th>{{ $header }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach($rows as $row)
                <tr>
                    @foreach($row as $cell)
                        <td>{!! nl2br(e((string) $cell)) !!}</td>
                    @endforeach
                </tr>
            @endforeach
        </tbody>
    </table>

    <!-- Page numbers in footer -->
    <script type="text/php">
        if (isset($pdf)) {
            $font = $fontMetrics->get_font("Cairo", "normal");
            $size = 9;
            $y = $pdf->get_height() - 28;
            $x = $pdf->get_width() / 2;
            $text = "صفحة {PAGE_NUM} من {PAGE_COUNT}";
            $pdf->page_text($x, $y, $text, $font, $size, [0.3,0.3,0.3], 0.0, 0.5, true);
        }
    </script>

    @if(isset($meta['footer']) && $meta['footer'])
        <div class="summary">{!! $meta['footer'] !!}</div>
    @endif
</body>
</html>
