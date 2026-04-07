<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>Export PDF</title>
    <style>
        @page {
            margin: 20px;
        }

        body {
            font-family: DejaVu Sans, sans-serif;
            direction: rtl;
            unicode-bidi: embed;
            text-align: right;
            font-size: 10px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            direction: rtl;
        }

        th, td {
            border: 1px solid #ccc;
            padding: 4px;
            text-align: right;
            vertical-align: middle;
            word-wrap: break-word;
        }

        th {
            background: #e9ecef;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <h3 style="text-align:center;">تصدير البيانات</h3>

    <table>
        <thead>
            <tr>
                @foreach($headers as $header)
                    <th>{{ $header }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach($rows as $row)
                <tr>
                    @foreach($row as $value)
                        <td>{{ $value }}</td>
                    @endforeach
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>