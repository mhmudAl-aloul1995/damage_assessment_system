<div class="card card-bordered shadow-sm mb-6">
    <div class="card-header border-0 pt-5">
        <h3 class="card-title fw-bold">{{ $section['title'] }}</h3>
    </div>
    <div class="card-body py-4">
        <div class="table-responsive">
            <table class="table table-rounded table-striped align-middle table-row-dashed fs-6 gy-5">
                <thead>
                    <tr class="text-start text-gray-500 fw-bold fs-7 text-uppercase gs-0">
                        <th class="min-w-250px">Question</th>
                        <th class="min-w-300px">Answer</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($section['rows'] as $row)
                        <tr>
                            <td class="fw-semibold text-gray-800">{{ $row['question'] }}</td>
                            <td class="{{ ($row['empty'] ?? false) ? 'text-muted' : 'text-gray-700' }}">
                                {{ $row['answer'] }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="2" class="text-center text-muted py-8">No data available.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
