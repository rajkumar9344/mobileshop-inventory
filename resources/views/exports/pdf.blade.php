<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>{{ $title ?? 'Export Data' }}</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Noto+Sans:wght@400;700&display=swap');
        
        body {
            font-family: 'Noto Sans', 'DejaVu Sans', Arial, sans-serif;
            font-size: 12px;
            margin: 20px;
            line-height: 1.4;
        }
        
        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #333;
            padding-bottom: 10px;
        }
        
        .header h1 {
            margin: 0;
            font-size: 18px;
            font-weight: 700;
        }
        
        .export-info {
            text-align: right;
            margin-bottom: 20px;
            font-size: 10px;
            color: #666;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
            font-size: 11px;
        }
        
        table, th, td {
            border: 1px solid #ddd;
        }
        
        th {
            background-color: #f8f9fa;
            font-weight: 700;
            padding: 8px 6px;
            text-align: left;
            font-size: 11px;
        }
        
        td {
            padding: 6px;
            vertical-align: top;
        }
        
        .currency {
            text-align: right;
            font-family: 'Noto Sans', 'DejaVu Sans', monospace;
        }
        
        .text-center {
            text-align: center;
        }
        
        .text-right {
            text-align: right;
        }
        
        .footer {
            margin-top: 30px;
            text-align: center;
            font-size: 10px;
            color: #666;
            border-top: 1px solid #ddd;
            padding-top: 10px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{ $title ?? 'Data Export' }}</h1>
    </div>
    
    <div class="export-info">
        Generated on: {{ now()->format('F j, Y \a\t g:i A') }}
    </div>
    
    <table>
        <thead>
            <tr>
                @if(isset($columns) && is_array($columns))
                    @foreach($columns as $column)
                        <th>{{ is_array($column) ? ($column['title'] ?? $column['data'] ?? '') : $column }}</th>
                    @endforeach
                @endif
            </tr>
        </thead>
        <tbody>
            @if(isset($data) && is_array($data))
                @foreach($data as $row)
                    <tr>
                        @foreach($row as $key => $value)
                            <td class="{{ (strpos(strtolower($key), 'amount') !== false) ? 'currency' : '' }}">
                                {{ $value }}
                            </td>
                        @endforeach
                    </tr>
                @endforeach
            @endif
        </tbody>
    </table>
    
    <div class="footer">
        {{ config('app.name', 'Application') }} | Exported Data Report
    </div>
</body>
</html>
