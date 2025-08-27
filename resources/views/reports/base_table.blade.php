<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <title>{{ $title }}</title>
    <style>
        @page { size: A4 portrait; margin: 20mm; }
        body { font-family: "Cairo", "DejaVu Sans", "Amiri", sans-serif; direction: rtl; unicode-bidi: bidi-override; color: #111; line-height: 1.8; }
        table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        thead th { background-color: #f3f4f6; color: #111; }
        th, td { border: 1px solid #aeb4bb; padding: 8px 10px; text-align: right; font-size: 12px; word-wrap: break-word; white-space: pre-line; }
        tr:nth-child(even) td { background-color: #fbfbfb; }
        .title-cell { background-color: #e5e7eb; font-weight: bold; font-size: 16px; text-align: center; padding: 10px; }
        .subtitle-cell { background-color: #f9fafb; font-size: 12px; text-align: center; color: #444; padding: 6px; }
        .summary { margin-top: 12px; padding: 10px; background-color: #f8f9fa; border: 1px solid #e5e7eb; border-radius: 4px; font-size: 12px; }
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
                    نظام السجلات الطبية - AWDA &nbsp;&nbsp;|&nbsp;&nbsp; تاريخ التقرير: {{ $generatedAt }}
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


