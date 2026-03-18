<?php $__env->startSection('title', 'الإستبيان'); ?>
<?php $__env->startSection('pageName', 'الإستبيان'); ?>

<?php $__env->startSection('content'); ?>
<?php
    $authType = auth()->user()->roles->first()->name; // eng | lawyer
    $isEngineer = $authType === 'Engineering Auditor';
    $pageTitle = $isEngineer ? 'Engineering Auditor' : 'Legal Auditor';
    
    $assignedColumnTitle = $isEngineer ? 'Engineer' : 'Lawyer';
?>

<div class="card card-flush mb-7">
    <div class="card-header align-items-center py-5 gap-2 gap-md-5">
        <div class="card-title">
            <h2 class="fw-bold"><?php echo e($pageTitle); ?></h2>
        </div>

        <div class="card-toolbar">
            <div class="d-flex justify-content-end" data-kt-user-table-toolbar="base">
                <button type="button" class="btn btn-sm btn-light-primary  me-3" id="resetFilters">
                    <i class="ki-duotone ki-arrows-circle fs-4">
                        <span class="path1"></span>
                        <span class="path2"></span>
                    </i>
                    تحديث
                </button>
            </div>
        </div>
    </div>

    <div class="card-body pt-0">
        <div class="table-responsive">
            <table class="table align-middle table-row-dashed fs-6 gy-5" id="auditTable">
                <thead>
                    <tr class="text-start text-muted fw-bold fs-7 text-uppercase gs-0">
                        <th width="40">#</th>
                        <th>Building Name</th>
                        <th>Owner</th>
                        <th>Municipality</th>
                        <th>Neighborhood</th>
                        <th>Status</th>
                        <th class="text-end min-w-100px">Actions</th>
                    </tr>
                </thead>
                <tbody class="text-gray-600 fw-semibold"></tbody>
            </table>
        </div>
    </div>
</div>
<?php $__env->stopSection(); ?>

<?php $__env->startSection('script'); ?>
<script>
    $(function () {
        let table = $('#auditTable').DataTable({
            processing: true,
            serverSide: true,
            ajax: {
                url: "<?php echo e(route('audit.auditBuilding')); ?>"
            },
            columns: [
                {
                    data: 'DT_RowIndex',
                    name: 'DT_RowIndex',
                    orderable: false,
                    searchable: false,
                    target: 0
                },
                {
                    data: 'building_name',
                    name: 'building_name'
                },
                
                {
                    data: 'owner_name',
                    name: 'owner_name'
                },
                {
                    data: 'municipalitie',
                    name: 'municipalitie'
                },
                {
                    data: 'neighborhood',
                    name: 'neighborhood'
                },
                {
                    data: 'status',
                    name: 'status',
                    orderable: false,
                    searchable: false
                },
                {
                    data: 'actions',
                    name: 'actions',
                    orderable: false,
                    searchable: false,
                    className: 'text-end'
                }
            ]
        });
$('#auditTable').on('draw.dt', function () {
    KTMenu.createInstances();
});
        $('#resetFilters').on('click', function () {
            table.ajax.reload();
        });
    });
</script>
<?php $__env->stopSection(); ?>
<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH D:\myProjects\phc\resources\views/DamageAssessment/auditBuilding.blade.php ENDPATH**/ ?>