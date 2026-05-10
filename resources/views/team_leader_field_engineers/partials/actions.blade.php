<div class="text-center">
    @can('team-leader-field-engineers.delete')
        <button type="button"
                class="btn btn-sm btn-light-danger delete-assignment"
                data-url="{{ route('admin.team-leader-field-engineers.destroy', $item->id) }}">
            حذف
        </button>
    @endcan
</div>