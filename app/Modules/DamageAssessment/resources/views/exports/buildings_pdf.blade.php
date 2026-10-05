 q<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Export PDF</title>
    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 10px;
        }

        h2 {
            text-align: center;
            margin-bottom: 12px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            table-layout: auto;
        }

        th {
            background-color: #1F4E78;
            color: #fff;
            border: 1px solid #ccc;
            padding: 6px;
            text-align: center;
        }

        td {
            border: 1px solid #ccc;
            padding: 5px;
            text-align: center;
            word-wrap: break-word;
        }

        tr:nth-child(even) {
            background: #f5f5f5;
        }
    </style>
</head>
<body>
    <h2>Buildings & Housing Export</h2>

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
                    @foreach((array) $row as $value)
                        <td>{{ $value }}</td>
                    @endforeach
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>