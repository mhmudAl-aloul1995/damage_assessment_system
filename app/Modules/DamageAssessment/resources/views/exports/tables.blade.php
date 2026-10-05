<style>
    table { width: 100%; border-collapse: collapse; margin-top: 20px; }
    th, td { border: 1px solid #000; padding: 8px; text-align: left; font-size: 12px; }
    /* Setting background-color here is more reliable for PDF engines */
    thead tr { background-color: #f2f2f2; } 
</style>

<table>
    <thead>
        <tr>
            @foreach ($bulding_coulmn as $colName)
                @php 
                    // Use the keyBy('name') collection from your controller
                    $assessment = $assessmentHints->get($colName); 
                @endphp
                <th>
                    {{-- Priority: Hint -> Label -> Original Column Name --}}
                    {{ $assessment->hint ?? ($assessment->label ?? str_replace('_', ' ', $colName)) }}
                </th>
            @endforeach
        </tr>
    </thead>
    <tbody>
        @foreach($building as $row)
            <tr>
                @foreach ($bulding_coulmn as $col)
                    <td>{{ $row->{$col} ?? 'N/A' }}</td>
                @endforeach
            </tr>
        @endforeach
    </tbody>
</table>
