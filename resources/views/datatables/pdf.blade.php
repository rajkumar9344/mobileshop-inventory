<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>{{ $title ?? 'Data Export' }}</title>
    <style>
        /* Use A4 portrait (default) and tighter margins */
        @page { size: A4 portrait; margin: 8mm; }

        body {
            font-family: 'dejavu sans', serif, Arial, sans-serif;
            font-size: 10px;
            margin: 6px;
            unicode-bidi: embed;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 6px;
            font-family: 'dejavu sans', serif, Arial, sans-serif;
            table-layout: fixed;
            font-size: 9px;
        }

        table, th, td {
            border: 1px solid #ddd;
        }

        th, td {
            padding: 4px 6px;
            text-align: left;
            font-family: 'dejavu sans', serif, Arial, sans-serif;
            unicode-bidi: embed;
            overflow: visible;
            white-space: normal;
            word-wrap: break-word;
            text-overflow: initial;
        }

        th {
            background-color: #f2f2f2;
            font-weight: bold;
        }

        .currency {
            font-family: 'dejavu sans', serif, Arial, sans-serif;
            text-align: right;
            direction: ltr;
            unicode-bidi: embed;
        }

        .text-center { text-align: center; }
        .text-right { text-align: right; }

        /* Ensure Unicode symbols render properly */
        .unicode-text { font-family: 'dejavu sans', serif, Arial, sans-serif; direction: ltr; unicode-bidi: embed; }

        h1 { font-family: serif, Arial, sans-serif; margin-bottom: 8px; font-size: 12px; }
    </style>
</head>
<body>
    @if(isset($title))
        <h1 style="font-family: 'dejavu sans', Arial, sans-serif;">{!! $title !!}</h1>
    @endif
    
    <table>
        <thead>
            <tr>
                @foreach($data as $row)
                    @if ($loop->first)
                        @foreach($row as $key => $value)
                            <th class="unicode-text">{!! $key !!}</th>
                        @endforeach
                    @endif
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach($data as $row)
                <tr>
                    @foreach($row as $key => $value)
                        <td class="{{ (strpos($key, 'amount') !== false || strpos($key, 'Amount') !== false || strpos($key, 'balance') !== false || strpos($key, 'Balance') !== false || strpos($key, 'total') !== false || strpos($key, 'Total') !== false) ? 'currency unicode-text' : 'unicode-text' }}">
                            {{ $value }}
                        </td>
                    @endforeach
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>