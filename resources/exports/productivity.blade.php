<table>
    <thead>
        <tr>
            <th>Day</th>
            @foreach ($period as $date)

                <th colspan="3">{{ $date->format('Y-m-d D') }}</th>
            @endforeach
            <th></th>
        </tr>
        <tr>
            <th> Eng.Name</th>
            @foreach ($period as $date)
                <th>TDA</th>
                <th>PDA</th>
                <th>Total</th>
            @endforeach
            <th>Total</th>
        </tr>
    </thead>
    <tbody>
        @php
            // These must be outside the loop to persist data across rows
            $columnTotals = [];
            $grandTotal = 0; 
        @endphp
        @foreach ($assignedto as $val)
            <tr>
                <td class="bg-warning">{{ $val }}</td>
                @foreach ($period as $date)
                    @php
                        $dayData = $stats[$val]['daily_breakdown'][$date->format('Y-m-d')] ?? null;

                        $pda = $dayData[0]['pda'] ?? 0;
                        $tda = $dayData[0]['tda'] ?? 0;
                    @endphp
                    <td class=" text-white bg-danger-active">{{ $pda }}</td>
                    <td class="text-white bg-success-active">{{ $tda }}</td>
                    <td class="text-white bg-primary-active">{{ $pda + $tda }}</td>
                @endforeach
                <td style=" background-color: gray; " class="text-white">
                    <b>@if (isset($stats[$val]))
                        {{  $stats[$val]['engineer_total']}}
                    @endif </b>
                </td>
            </tr>
        @endforeach
    </tbody>
    <tfoot>
        <tr style="background-color: #ffc107; font-weight: bold;">
            <td>الإجمالي (Total)</td>
            @foreach ($period as $date)
                @php $dateStr = $date->format('Y-m-d'); @endphp
                <td>{{ $columnTotals[$dateStr]['pda'] ?? 0 }}</td>
                <td>{{ $columnTotals[$dateStr]['tda'] ?? 0 }}</td>
                <td>{{ $columnTotals[$dateStr]['total'] ?? 0 }}</td>
            @endforeach
            <td class="bg-info text-white">
                <b>{{ $grandTotal }}</b>
            </td>
        </tr>
    </tfoot>
</table>