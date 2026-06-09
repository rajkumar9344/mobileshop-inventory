<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Barcodes PDF</title>
    <style>
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #ccc; padding: 8px; }
        th { background: #f5f5f5; }
        td { text-align: center; }
    </style>
</head>
<body>
    <h2>Barcodes PDF</h2>
    <table>
        <thead>
            <tr>
                <th>Product Name</th>
                <th>Price</th>
                <th>Barcode</th>
            </tr>
        </thead>
        <tbody>
            @foreach($barcodes as $barcode)
                <tr>
                    <td>{{ $barcode['name'] }}</td>
                    <td>{{ $barcode['price'] }}</td>
                    <td>{!! '<img src="data:image/png;base64,' . $barcode['barcode'] . '" alt="Barcode" style="height: 60px;" />' !!}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
