

<?php $__env->startSection('title', 'Attendance'); ?>
<?php $__env->startSection('pageName', 'Attendance'); ?>

<?php $__env->startSection('content'); ?>
<style>
    .attendance-sheet-head {
        display: grid;
        grid-template-columns: 220px 1fr 320px;
        align-items: center;
        gap: 0;
        border: 1px solid #2b2b2b;
        border-bottom: 0;
        background: #fff;
        direction: rtl;
    }

    .sheet-title-left,
    .sheet-title-center,
    .sheet-title-right {
        min-height: 86px;
        display: flex;
        flex-direction: column;
        justify-content: center;
        border-left: 1px solid #2b2b2b;
        padding: 8px 12px;
        font-weight: 700;
        color: #000;
    }

    .sheet-title-right {
        text-align: right;
    }

    .sheet-title-center {
        text-align: center;
        font-size: 22px;
    }

    .sheet-title-left {
        text-align: center;
        font-size: 16px;
        line-height: 1.5;
        border-left: 0;
    }

    .sheet-main-title {
        font-size: 24px;
        font-weight: 800;
    }

    /* ✅ الحاوية RTL فقط */
    .attendance-rtl-wrapper {
        direction: rtl !important;
        overflow-x: auto;
    }

    /* ✅ الجدول نفسه LTR حتى يطابق DataTables */
    #attendanceTable,
    #attendanceTable_wrapper,
    #attendanceTable_wrapper .dataTables_scroll,
    #attendanceTable_wrapper .dataTables_scrollHead,
    #attendanceTable_wrapper .dataTables_scrollBody,
    #attendanceTable_wrapper table {
        direction: ltr !important;
    }

    #attendanceTable {
        width: 100% !important;
        border-collapse: collapse !important;
        margin-top: 0 !important;
    }

    #attendanceTable thead th,
    #attendanceTable tbody td {
        white-space: nowrap;
        vertical-align: middle !important;
        border: 1px solid #2b2b2b !important;
    }

    #attendanceTable thead .header-row th {
        background: #f7c400 !important;
        color: #000 !important;
        font-weight: 700;
        text-align: center;
        padding: 6px 4px !important;
    }

    #attendanceTable tbody td {
        background: #eef0c9;
        padding: 4px 6px !important;
        font-size: 13px;
        text-align: center;
    }

    #attendanceTable tbody tr:nth-child(even) td.base-col {
        background: #dce8ee !important;
    }

    #attendanceTable tbody tr:nth-child(odd) td.base-col {
        background: #f5e5d8 !important;
    }

    #attendanceTable tbody td.total-col {
        background: #efeec5 !important;
        font-weight: 700;
    }

    #attendanceTable tbody td.day-col {
        background: #eef0c9 !important;
        text-align: center !important;
        padding: 3px !important;
    }

    .name-en {
        color: #ff0000;
        text-align: left !important;
        direction: ltr !important;
    }

    .name-ar {
        color: #ff0000;
        text-align: right !important;
        direction: rtl !important;
    }

    .position-col {
        color: #ff0000;
        text-align: left !important;
        direction: ltr !important;
    }

    .day-head .day-name {
        font-size: 11px;
        font-weight: 700;
        margin-bottom: 2px;
    }

    .day-head .day-number {
        font-size: 18px;
        font-weight: 700;
    }

    .day-badge {
        display: inline-block;
        min-width: 24px;
        padding: 2px 6px;
        border-radius: 4px;
        font-weight: 700;
        line-height: 1.2;
        color: #000;
        background: transparent;
    }

    #attendanceTable tbody tr:hover td {
        background-color: #f7f1b5 !important;
    }

    .dataTables_wrapper .dataTables_paginate,
    .dataTables_wrapper .dataTables_info,
    .dataTables_wrapper .dataTables_filter,
    .dataTables_wrapper .dataTables_length {
        display: none !important;
    }
</style>

<div class="card card-flush mb-7">
    <div class="card-header align-items-center py-5 gap-2 gap-md-5">
        <div class="card-title">
            <h2 class="fw-bold">Attendance Sheet</h2>
        </div>

        <div class="card-toolbar">
            <div class="d-flex align-items-center gap-2 me-3">
                <select id="year" class="form-select form-select-sm w-auto">
                    <option value="">Year</option>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php for($y = now()->year; $y >= now()->year - 5; $y--): ?>
                        <option value="<?php echo e($y); ?>"><?php echo e($y); ?></option>
                    <?php endfor; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </select>

                <select id="month" class="form-select form-select-sm w-auto">
                    <option value="">Month</option>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php for($m = 1; $m <= 12; $m++): ?>
                        <option value="<?php echo e($m); ?>"><?php echo e($m); ?></option>
                    <?php endfor; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </select>

                <button type="button" id="reloadTable" class="btn btn-sm btn-light-primary">
                    <i class="ki-duotone ki-arrows-circle">
                        <span class="path1"></span>
                        <span class="path2"></span>
                    </i>
                    Reload
                </button>
            </div>
        </div>
    </div>

    <div class="card-body">

        <div class="attendance-sheet-head mb-4">
            <div class="sheet-title-right">
                <div class="sheet-main-title">
                    Attendance Sheet - <span id="sheetMonthYearText">Month Year</span>
                </div>
            </div>

            <div class="sheet-title-center">
                Damage Assessment Team
            </div>

            <div class="sheet-title-left">
                <div>Attendance</div>
                <div id="sheetMonthLabel">Month</div>
                <div id="sheetYearLabel">Year</div>
            </div>
        </div>

        <div class="attendance-rtl-wrapper">
            <table id="attendanceTable" class="table table-bordered align-middle text-center fs-7" style="width:100%">
                <thead>
                    <tr class="header-row">
                        <th class="w-50px min-w-50px">No.</th>
                        <th class="w-125px min-w-125px">Contract<br>Start Date</th>
                        <th class="w-200px min-w-200px">Name English</th>
                        <th class="w-200px min-w-200px">Name Arabic</th>
                        <th class="w-150px min-w-150px">Position</th>
                        <th class="w-125px min-w-125px">ID No.</th>
                        <th class="w-100px min-w-100px">UNDP/PHC</th>
                        <th class="w-125px min-w-125px">Contact number</th>
                        <th class="w-110px min-w-110px">Total Attendance<br>(Day)</th>

                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php for($i = 1; $i <= 31; $i++): ?>
                            <th class="day-head w-45px min-w-45px">
                                <div class="day-name" id="day_name_<?php echo e($i); ?>"></div>
                                <div class="day-number"><?php echo e($i); ?></div>
                            </th>
                        <?php endfor; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </tr>
                </thead>
            </table>
        </div>
    </div>
</div>
<?php $__env->stopSection(); ?>

<?php $__env->startSection('script'); ?>
<script>
    let table;

    const monthNames = {
        1: 'January', 2: 'February', 3: 'March', 4: 'April',
        5: 'May', 6: 'June', 7: 'July', 8: 'August',
        9: 'September', 10: 'October', 11: 'November', 12: 'December'
    };

    const dayNames = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];

    function updateSheetHeader() {
        let month = $('#month').val() || '<?php echo e(now()->month); ?>';
        let year = $('#year').val() || '<?php echo e(now()->year); ?>';

        $('#sheetMonthLabel').text(monthNames[month] ?? 'Month');
        $('#sheetYearLabel').text(year);
        $('#sheetMonthYearText').text((monthNames[month] ?? 'Month') + ' ' + year);

        for (let i = 1; i <= 31; i++) {
            let d = new Date(year, month - 1, i);

            if ((d.getMonth() + 1) == month) {
                $('#day_name_' + i).text(dayNames[d.getDay()]);
            } else {
                $('#day_name_' + i).text('');
            }
        }
    }

    $(function () {
        updateSheetHeader();

        table = $('#attendanceTable').DataTable({
            processing: true,
            serverSide: true,
            scrollX: true,
            scrollCollapse: true,
            autoWidth: false,
            paging: false,
            searching: false,
            ordering: false,
            info: false,
            ajax: {
                url: "<?php echo e(route('attendance.data')); ?>",
                type: "POST",
                data: function (d) {
                    d._token = "<?php echo e(csrf_token()); ?>";
                    d.month = $('#month').val();
                    d.year = $('#year').val();
                }
            },

            /* ✅ لا تقلب الترتيب */
            columns: [
                { data: 'DT_RowIndex', className: 'text-center' },
                { data: 'contract_date', className: 'text-center' },
                { data: 'name_en', className: 'text-start' },
                { data: 'name_ar', className: 'text-end' },
                { data: 'position', className: 'text-start' },
                { data: 'id_no', className: 'text-center' },
                {
                    data: null,
                    className: 'text-center',
                    render: function () {
                        return 'PDA- PHC';
                    }
                },
                { data: 'contact', className: 'text-center' },
                { data: 'total', className: 'text-center' },

                <?php for($i = 1; $i <= 31; $i++): ?>
                {
                    data: 'day_<?php echo e($i); ?>',
                    defaultContent: '',
                    className: 'text-center',
                    render: function(data) {
                        let month = $('#month').val() || '<?php echo e(now()->month); ?>';
                        let year = $('#year').val() || '<?php echo e(now()->year); ?>';
                        let day = <?php echo e($i); ?>;
                        let d = new Date(year, month - 1, day);

                        if ((d.getMonth() + 1) != month) {
                            return '';
                        }

                        if (data === '' || data === null || typeof data === 'undefined') {
                            return '<span class="day-badge"></span>';
                        }

                        return '<span class="day-badge">' + data + '</span>';
                    }
                },
                <?php endfor; ?>
            ],

            createdRow: function(row, data) {
                $('td', row).eq(0).addClass('base-col');
                $('td', row).eq(1).addClass('base-col');
                $('td', row).eq(2).addClass('base-col name-en');
                $('td', row).eq(3).addClass('base-col name-ar');
                $('td', row).eq(4).addClass('base-col position-col');
                $('td', row).eq(5).addClass('base-col');
                $('td', row).eq(6).addClass('base-col');
                $('td', row).eq(7).addClass('base-col');
                $('td', row).eq(8).addClass('total-col');

                for (let i = 9; i <= 39; i++) {
                    $('td', row).eq(i).addClass('day-col');
                }
            },

            drawCallback: function() {
                updateSheetHeader();
                table.columns.adjust();
            }
        });

        $('#reloadTable').on('click', function () {
            const btn = $(this);
            btn.attr('data-kt-indicator', 'on');
            btn.prop('disabled', true);

            updateSheetHeader();

            table.ajax.reload(function () {
                table.columns.adjust();
                btn.removeAttr('data-kt-indicator');
                btn.prop('disabled', false);
            }, false);
        });

        $('#month, #year').on('change', function () {
            updateSheetHeader();
            table.ajax.reload(function () {
                table.columns.adjust();
            }, false);
        });
    });
</script>
<?php $__env->stopSection(); ?>
<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH D:\myProjects\phc\resources\views/DamageAssessment/attendance.blade.php ENDPATH**/ ?>