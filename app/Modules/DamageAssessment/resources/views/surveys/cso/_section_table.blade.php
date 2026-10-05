<details class="cso-section border border-gray-200 rounded mb-4" @if ($open ?? false) open @endif>
    <summary class="bg-light rounded px-5 py-4 fw-bold text-gray-800 fs-6">
        <span>{{ $section['title'] }}</span>
        <i class="ki-duotone ki-down fs-3 cso-chevron" aria-hidden="true"></i>
    </summary>

    <div class="cso-section-body px-5 py-2">
        <table class="table table-row-dashed table-row-gray-200 align-middle gy-4 mb-0 cso-fields-table">
            <thead class="visually-hidden">
                <tr>
                    <th scope="col">{{ __('cso_details.question') }}</th>
                    <th scope="col">{{ __('cso_details.answer') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($section['rows'] as $row)
                    <tr>
                        <th scope="row" class="text-gray-600 fw-semibold pe-4">{{ $row['question'] }}</th>
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
