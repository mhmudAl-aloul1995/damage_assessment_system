<details class="cso-section" @if ($open ?? false) open @endif>
    <summary>{{ $section['title'] }}</summary>

    <div class="cso-section-body">
        <table class="cso-fields-table">
            <thead class="visually-hidden">
                <tr>
                    <th scope="col">{{ __('cso_details.question') }}</th>
                    <th scope="col">{{ __('cso_details.answer') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($section['rows'] as $row)
                    <tr>
                        <th scope="row">{{ $row['question'] }}</th>
                        <td class="{{ ($row['empty'] ?? false) ? 'cso-muted' : '' }}">{{ $row['answer'] }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="2" class="cso-muted">{{ __('cso_details.no_data') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</details>
