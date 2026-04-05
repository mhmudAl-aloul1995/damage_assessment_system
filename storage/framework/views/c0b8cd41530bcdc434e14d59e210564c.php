<?php $__env->startSection('title', 'الإستبيان'); ?>
<?php $__env->startSection('pageName', 'الإستبيان'); ?>

<?php $__env->startSection('content'); ?>
    <?php
        $authType = auth()->user()->roles->first()->name; // eng | lawyer
        $isEngineer = $authType === 'QC/QA Engineer';

        $pageTitle = $isEngineer ? 'QC/QA Engineer' : 'Legal Auditor';

        $assignedColumnTitle = $isEngineer ? 'Engineer' : 'Lawyer';
    ?>

    <div class="row mb-5">
        <div class="col-md-12">
            <div class="card card-flush shadow-sm">
                <div class="card-header pt-6">
                    <div class="card-title">
                        <i class="ki-duotone ki-filter fs-1 me-3 text-primary"></i>
                        <h3 class="fw-bold m-0">الفلاتر</h3>
                    </div>

                    <div class="card-toolbar">
                        <button type="button" class="btn btn-sm btn-light-danger" id="resetFilters">
                            إعادة تعيين
                        </button>
                    </div>
                </div>

                <div class="card-body">
                    <div class="row g-5">
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">بحث باسم المبنى</label>
                            <input type="text" id="filter_building_name" class="form-control form-control-solid"
                                placeholder="اسم المبنى" />
                        </div>



                        <div class="col-md-3">
                            <label class="form-label fw-semibold">الحالة الهندسية</label>
                            <select id="filter_eng_status" class="form-select form-select-solid" data-control="select2"
                                data-allow-clear="true" data-placeholder="اختر الحالة">
                                <option></option>
                                <option value="pending">Pending</option>
                                <option value="accepted_by_engineer">Accepted By Engineer</option>
                                <option value="rejected_by_engineer">Rejected By Engineer</option>
                                <option value="assigned_to_engineer">Assigned To Engineer</option>
                                <option value="need_review">Need Review</option>
                            </select>
                        </div>

                        <div class="col-md-3">
                            <label class="form-label fw-semibold">الحالة القانونية</label>
                            <select id="filter_legal_status" class="form-select form-select-solid" data-control="select2"
                                data-allow-clear="true" data-placeholder="اختر الحالة">
                                <option></option>
                                <option value="pending">Pending</option>
                                <option value="accepted_by_lawyer">Accepted By Lawyer</option>
                                <option value="legal_notes">Legal Notes</option>
                            </select>
                        </div>


                        <div class="col-md-3">
                            <label class="form-label fw-semibold">منطقة/حي</label>
                            <input type="text" id="filter_area" class="form-control form-control-solid"
                                placeholder="المنطقة أو الحي" />
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">المهندس الميداني </label>
                            <select id="filter_field_engineer" class="form-select form-select-solid" data-control="select2"
                                data-allow-clear="true" data-placeholder="اختر Field Engineer">
                                <option></option>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $assignedTo; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $eng): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoop($loop->index); ?><?php endif; ?>
                                    <option value="<?php echo e($eng->assignedto); ?>"><?php echo e($eng->assignedto); ?></option>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                            </select>
                        </div>

                        <div class="col-md-3">
                            <label class="form-label fw-semibold">حالة الضرر</label>
                            <select id="filter_damage_status" class="form-select form-select-solid" data-control="select2"
                                data-allow-clear="true" data-placeholder="اختر الحالة">
                                <option></option>
                                <option value="fully_damaged">Fully Damaged</option>
                                <option value="partially_damaged">Partially Damaged</option>
                                <option value="committee_review">Committee Review </option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">من تاريخ الإنشاء</label>
                            <input type="date" id="filter_from_date" placeholder="من تاريخ الإنشاء"
                                class="form-control form-control-solid">
                        </div>

                        <div class="col-md-3">
                            <label class="form-label fw-semibold">إلى تاريخ الإنشاء</label>
                            <input type="date" id="filter_to_date" placeholder="إلى تاريخ الإنشاء"
                                class="form-control form-control-solid">
                        </div>
                        <div class="col-md-3 d-flex align-items-end">
                            <button type="button" class="btn btn-primary w-100" id="applyFilters">
                                تطبيق الفلاتر
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="row">
        <div class="col-md-12">
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
                                    <th>Field Engineer</th>
                                    <th>Municipality</th>
                                    <th>Neighborhood</th>
                                    <th>Status</th>
                                    <th>Creation Date</th>
                                    <th class="text-end min-w-100px">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="text-gray-600 fw-semibold"></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
<?php $__env->stopSection(); ?>

<?php $__env->startSection('script'); ?>
    <script>
        let fromPicker = flatpickr("#filter_from_date", {
            dateFormat: "Y-m-d",
            allowInput: true,
            onChange: function (selectedDates) {
                toPicker.set('minDate', selectedDates[0]);
            }
        });

        let toPicker = flatpickr("#filter_to_date", {
            dateFormat: "Y-m-d",
            allowInput: true,
            onChange: function (selectedDates) {
                fromPicker.set('maxDate', selectedDates[0]);
            }
        });
        $(function () {
            let table = $('#auditTable').DataTable({
                processing: true,
                serverSide: true,
                ajax: {
                    url: "<?php echo e(route('audit.auditBuilding')); ?>",
                    data: function (d) {
                        d.building_name = $('#filter_building_name').val();
                        d.engineer_id = $('#filter_engineer').val();
                        d.lawyer_id = $('#filter_lawyer').val();
                        d.eng_status = $('#filter_eng_status').val();
                        d.legal_status = $('#filter_legal_status').val();
                        d.final_status = $('#filter_final_status').val();
                        d.area = $('#filter_area').val();
                        d.field_engineer = $('#filter_field_engineer').val();
                        d.damage_status = $('#filter_damage_status').val();
                        d.filter_from_date = $('#filter_from_date').val();
                        d.filter_to_date = $('#filter_to_date').val();
                    }
                },
                columns: [{
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
                    data: 'assignedto',
                    name: 'assignedto'
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
                    data: 'creationdate',
                    name: 'creationdate',
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

            $('#applyFilters').on('click', function () {

                let btn = $(this);

                btn.attr('data-kt-indicator', 'on');
                btn.prop('disabled', true);

                table.ajax.reload(function () {
                    // ✅ يرجع الزر طبيعي بعد التحميل
                    btn.removeAttr('data-kt-indicator');
                    btn.prop('disabled', false);
                });

            });
            $('#resetFilters').on('click', function () {
                /* 			$('#filter_building_name').val('');
                            $('#filter_engineer').val(null).trigger('change');
                            $('#filter_lawyer').val(null).trigger('change');
                            $('#filter_eng_status').val(null).trigger('change');
                            $('#filter_legal_status').val(null).trigger('change');
                            $('#filter_final_status').val(null).trigger('change');
                            $('#filter_area').val(''); */
                $('select').val(null).trigger('change');
                $('input').val('');


            });
            // Link custom search input
            $('#tableSearch').keyup(function () {
                table.search($(this).val()).draw();
            });

        });
    </script>
<?php $__env->stopSection(); ?>
<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH D:\myProjects\phc\resources\views/DamageAssessment/auditBuilding.blade.php ENDPATH**/ ?>