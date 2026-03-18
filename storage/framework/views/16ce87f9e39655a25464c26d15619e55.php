<?php $__env->startSection('title', 'الإستبيان'); ?>
<?php $__env->startSection('pageName', 'الإستبيان'); ?>

<?php $__env->startSection('content'); ?>

<div class="card card-flush mb-7">
    <div class="card-header pt-7">
        <div class="card-title">
            <h2>الإستبيان</h2>
        </div>
    </div>

    <div class="card-body">
        <ul class="nav nav-stretch nav-line-tabs nav-line-tabs-2x border-transparent fs-5 fw-bold mb-8">
            <li onclick="$('#kt_table_building_assessment').DataTable().ajax.reload()" class="nav-item">
                <a  class="nav-link text-active-primary active" data-bs-toggle="tab" href="#tab_building">
                    المبنى
                </a>
            </li>
            <li onclick="$('#kt_table_housing_assessment').DataTable().ajax.reload()" class="nav-item">
                <a class="nav-link text-active-primary" data-bs-toggle="tab" href="#tab_housing">
                    الوحدة السكنية
                </a>
            </li>
        </ul>

        <div class="tab-content">
            <div class="tab-pane fade show active" id="tab_building" role="tabpanel">
                <div class="card">
                    <div class="card-header border-0 pt-6">
                        <div class="card-title">
                            <div class="d-flex align-items-center position-relative my-1">
                                <i class="ki-duotone ki-magnifier fs-3 position-absolute ms-5">
                                    <span class="path1"></span>
                                    <span class="path2"></span>
                                </i>
                                <input
                                    type="text"
                                    data-kt-buildingAssessment-table-filter="search"
                                    class="form-control form-control-solid w-200px ps-13"
                                    placeholder="بحث" />
                            </div>
                        </div>

                        <div class="card-toolbar">
                            <div class="d-flex justify-content-end" data-kt-Building-table-toolbar="base">
                                <button
                                    type="button"
                                    class="btn btn-light-primary me-3"
                                    onclick="$('#kt_table_building_assessment').DataTable().ajax.reload()">
                                    <i class="ki-duotone ki-arrows-circle fs-2">
                                        <span class="path1"></span>
                                        <span class="path2"></span>
                                    </i>
                                    تحديث
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="card-body py-4">
                        <table class="table table-rounded table-striped align-middle table-row-dashed fs-6 gy-5"
                               id="kt_table_building_assessment">
                            <thead>
                                <tr class="text-start text-muted fw-bold border-bottom border-gray-200 fs-7 text-uppercase gs-0">
                                    <th class="text-start min-w-250px">السؤال</th>
                                    <th class="text-center min-w-200px">الجواب</th>
                                    <th class="text-center min-w-250px">تعديل الإجابة</th>
                                </tr>
                            </thead>
                            <tbody class="text-gray-600 fw-semibold"></tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="tab-pane fade" id="tab_housing" role="tabpanel">
                <div class="card">
                    <div class="card-header border-0 pt-6">
                        <div class="card-title">
                            <div class="d-flex align-items-center position-relative my-1">
                                <i class="ki-duotone ki-magnifier fs-3 position-absolute ms-5">
                                    <span class="path1"></span>
                                    <span class="path2"></span>
                                </i>
                                <input
                                    type="text"
                                    data-kt-HousingAssessment-table-filter="search"
                                    class="form-control form-control-solid w-150px ps-13"
                                    placeholder="بحث" />
                            </div>
                        </div>

                        <div class="card-toolbar">
                            <div class="d-flex align-items-center fw-bold">
                                <div class="d-flex justify-content-end me-3" data-kt-HousingAssessment-table-toolbar="base">
                                    <select
                                        name="globalid"
                                        data-kt-globalid-table-filter="search"
                                        class="form-select form-select-transparent text-gray-800 fs-base lh-1 fw-bold py-0 ps-3 w-auto"
                                        data-control="select2"
                                        data-allow-clear="true"
                                        data-dropdown-css-class="w-200px"
                                        data-placeholder="إختر الوحدة">
                                        <option value=""></option>
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $HousingUnit; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $value): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoop($loop->index); ?><?php endif; ?>
                                            <option value="<?php echo e($value->globalid); ?>">
                                                <?php echo e($value->objectid . '--' . $value->full_name); ?>

                                            </option>
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                                    </select>
                                </div>

                                <button
                                    type="button"
                                    class="btn btn-md btn-light-primary me-3"
                                    onclick="$('#kt_table_housing_assessment').DataTable().ajax.reload()">
                                    <i class="ki-duotone ki-arrows-circle fs-2">
                                        <span class="path1"></span>
                                        <span class="path2"></span>
                                    </i>
                                    تحديث
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="card-body py-4">
                        <table class="table table-rounded table-striped align-middle table-row-dashed fs-6 gy-5"
                               id="kt_table_housing_assessment">
                            <thead>
                                <tr class="text-start text-muted fw-bold border-bottom border-gray-200 fs-7 text-uppercase gs-0">
                                    <th class="text-start min-w-250px">السؤال</th>
                                    <th class="text-center min-w-200px">الجواب</th>
                                    <th class="text-center min-w-250px">تعديل الإجابة</th>
                                </tr>
                            </thead>
                            <tbody class="text-gray-600 fw-semibold"></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php $__env->stopSection(); ?>

<?php $__env->startSection('script'); ?>
<script>
    function initInlineEditors() {
        $('.inline-edit-select').each(function () {
            if (!$(this).hasClass('select2-hidden-accessible')) {
                $(this).select2({
                    minimumResultsForSearch: 0,
                    width: '100%',
                    dir: 'rtl'
                });
            }
        });
    }

    function saveInlineValue(field, globalid, type, value, callback = null) {
        $.ajax({
            url: "<?php echo e(route('assessment.inline.update')); ?>",
            method: "POST",
            data: {
                _token: "<?php echo e(csrf_token()); ?>",
                field: field,
                globalid: globalid,
                type: type,
                value: value
            },
            success: function (response) {
                if (typeof toastr !== 'undefined') {
                    toastr.success(response.message || 'تم الحفظ بنجاح');
                }

                
                if (callback) {
                    callback(true);
                }
                 if (type === 'building_table') {
                    $('#kt_table_building_assessment').DataTable().ajax.reload(null);
                } else if (type === 'housing_table') {
                    $('#kt_table_housing_assessment').DataTable().ajax.reload(null);
                }
            },
            error: function (xhr) {
                let message = 'حدث خطأ أثناء الحفظ';

                if (xhr.responseJSON && xhr.responseJSON.message) {
                    message = xhr.responseJSON.message;
                }

                if (typeof toastr !== 'undefined') {
                    toastr.error(message);
                } else {
                    alert(message);
                }

                if (callback) {
                    callback(false);
                }
            }
        });
    }

    $(document).on('click', '.inline-save-btn', function () {
        let btn = $(this);
        let wrapper = btn.closest('.d-flex');
        let input = wrapper.find('.inline-edit-input');

        let field = btn.data('field');
        let globalid = btn.data('globalid');
        let type = btn.data('type');
        let value = input.val();

        btn.prop('disabled', true).html('...');

        saveInlineValue(field, globalid, type, value, function () {
            btn.prop('disabled', false).html('حفظ');
        });
    });

    $(document).on('change', '.inline-edit-select', function () {
        let select = $(this);

        let field = select.data('field');
        let globalid = select.data('globalid');
        let type = select.data('type');
        let value = select.val();

        saveInlineValue(field, globalid, type, value);
    });

    var KTBuildingAssessmentList = function () {
        var table = document.getElementById('kt_table_building_assessment');
        var datatable;

        var initEngineerTable = function () {
            datatable = $(table).DataTable({
                serverSide: true,
                ajax: {
                    url: "<?php echo e(url('showBuildings')); ?>",
                    data: function (d) {
                        d.globalid = '<?php echo e($globalid); ?>';
                    },
                },
                info: false,
                order: [],
                pageLength: 200,
                processing: true,
                columns: [
                    { className: 'text-start', data: 'question', name: 'question', searchable: false, orderable: false },
                    { className: 'text-center', data: 'answer', name: 'answer', searchable: false, orderable: false },
                    { className: 'text-center', data: 'editAnswer', name: 'editAnswer', searchable: false, orderable: false },
                ],
                createdRow: (row) => {
                    $(row).css('cursor', 'default');
                }
            });

            datatable.on('draw', function () {
                if (typeof KTMenu !== 'undefined') {
                    KTMenu.createInstances();
                }
                initInlineEditors();
            });
        }

        var handleSearchDatatable = () => {
            const filterSearch = document.querySelector('[data-kt-buildingAssessment-table-filter="search"]');

            if (!filterSearch) return;

            filterSearch.addEventListener('keydown', function (e) {
                if (e.which == 13) {
                    e.preventDefault();
                    datatable.search(e.target.value).draw();
                }
            });
        }

        return {
            init: function () {
                if (!table) return;
                initEngineerTable();
                handleSearchDatatable();
            }
        }
    }();

    var KTHousingAssessmentList = function () {
        var table = document.getElementById('kt_table_housing_assessment');
        var datatable;

        var initHousingTable = function () {
            datatable = $(table).DataTable({
                serverSide: true,
                ajax: {
                    url: "<?php echo e(url('showHousings')); ?>",
                    data: function (d) {
                        d.parentglobalid = '<?php echo e($globalid); ?>';
                        d.globalid = $("[name='globalid']").val();
                    },
                },
                info: false,
                order: [],
                pageLength: 16,
                processing: true,
                columns: [
                    { className: 'text-start', data: 'question', name: 'question', searchable: false, orderable: false },
                    { className: 'text-center', data: 'answer', name: 'answer', searchable: false, orderable: false },
                    { className: 'text-center', data: 'editAnswer', name: 'editAnswer', searchable: false, orderable: false },
                ],
                createdRow: (row) => {
                    $(row).css('cursor', 'default');
                }
            });

            datatable.on('draw', function () {
                if (typeof KTMenu !== 'undefined') {
                    KTMenu.createInstances();
                }
                initInlineEditors();
            });
        }

        var handleSearchDatatable = () => {
            const filterSearch = document.querySelector('[data-kt-HousingAssessment-table-filter="search"]');

            if (!filterSearch) return;

            filterSearch.addEventListener('keydown', function (e) {
                if (e.which == 13) {
                    e.preventDefault();
                    datatable.search(e.target.value).draw();
                }
            });
        }

        var handleChangeHousingUnit = () => {
            const filterSearch = $('[name="globalid"]');

            filterSearch.on("change", function () {
                datatable.ajax.reload();
            });
        }

        return {
            init: function () {
                if (!table) return;
                initHousingTable();
                handleSearchDatatable();
                handleChangeHousingUnit();
            }
        }
    }();

    KTUtil.onDOMContentLoaded(function () {
        KTBuildingAssessmentList.init();
        KTHousingAssessmentList.init();
        initInlineEditors();

        $('a[data-bs-toggle="tab"]').on('shown.bs.tab', function () {
            $.fn.dataTable.tables({ visible: true, api: true }).columns.adjust();
            initInlineEditors();
        });
    });
</script>
<?php $__env->stopSection(); ?>
<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH D:\myProjects\phc\resources\views/DamageAssessment/assessmentAudit.blade.php ENDPATH**/ ?>