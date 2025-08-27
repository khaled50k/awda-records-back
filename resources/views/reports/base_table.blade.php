<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <title>{{ $title }}</title>
    <style>
        @page { margin: 20px; }
        body { font-family: "DejaVu Sans", "Amiri", "Arial", sans-serif; direction: rtl; unicode-bidi: bidi-override; color: #111; }
        .header { text-align: center; margin-bottom: 20px; border-bottom: 2px solid #333; padding-bottom: 10px; }
        .header h1 { color: #333; margin: 0; font-size: 20px; }
        .header p { color: #666; margin: 4px 0; font-size: 12px; }
        .meta { margin-top: 8px; font-size: 12px; color: #333; }
        table { width: 100%; border-collapse: collapse; margin-top: 12px; table-layout: fixed; }
        th, td { border: 1px solid #bbb; padding: 6px; text-align: right; font-size: 11px; word-wrap: break-word; line-height: 1.6; }
        th { background-color: #f2f2f2; font-weight: bold; }
        tr:nth-child(even) { background-color: #fbfbfb; }
        .summary { margin-top: 14px; padding: 10px; background-color: #f8f9fa; border-radius: 4px; font-size: 12px; }
        .summary h3 { margin: 0 0 6px 0; color: #333; font-size: 14px; }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{ $title }}</h1>
        <p>نظام السجلات الطبية - AWDA</p>
        <p>تاريخ التقرير: {{ $generatedAt }}</p>
        @if(!empty($meta))
            <div class="meta">
                @if(isset($meta['date_range']))
                    <div>الفترة: من {{ $meta['date_range']['from_date'] ?? '' }} إلى {{ $meta['date_range']['to_date'] ?? '' }}</div>
                @endif
                @if(isset($meta['summary']))
                    <div>
                        الإجمالي: المرضى {{ $meta['summary']['total_patients'] ?? 0 }} | السجلات {{ $meta['summary']['total_records'] ?? 0 }} | التحويلات {{ $meta['summary']['total_transfers'] ?? 0 }}
                    </div>
                @endif
            </div>
        @endif
    </div>

    <table>
        <tr>
            @foreach($headers as $header)
                <th>{{ $header }}</th>
            @endforeach
        </tr>
        @foreach($rows as $row)
            <tr>
                @foreach($row as $cell)
                    <td>{!! nl2br(e((string) $cell)) !!}</td>
                @endforeach
            </tr>
        @endforeach
    </table>

    @if(isset($meta['footer']) && $meta['footer'])
        <div class="summary">{!! $meta['footer'] !!}</div>
    @endif
</body>
</html>


