<?php $__env->startSection('title', 'Public Buildings'); ?>
<?php $__env->startSection('pageName', 'Public Buildings'); ?>

<?php $__env->startSection('content'); ?>
    <div class="row g-5 mb-5">
        <div class="col-md-4">
            <div class="card card-flush border border-gray-200 h-100">
                <div class="card-body text-center d-flex flex-column justify-content-center">
                    <div class="text-muted fs-6 mb-2">Total Surveys</div>
                    <div class="fs-2hx fw-bold text-gray-900"><?php echo e($summary['total_surveys']); ?></div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card card-flush border border-gray-200 h-100">
                <div class="card-body text-center d-flex flex-column justify-content-center">
                    <div class="text-muted fs-6 mb-2">Repeated Units</div>
                    <div class="fs-2hx fw-bold text-primary"><?php echo e($summary['total_units']); ?></div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card card-flush border border-gray-200 h-100">
                <div class="card-body text-center d-flex flex-column justify-content-center">
                    <div class="text-muted fs-6 mb-2">Damaged Buildings</div>
                    <div class="fs-2hx fw-bold text-danger"><?php echo e($summary['damaged_buildings']); ?></div>
                </div>
            </div>
        </div>
    </div>

    <div class="card card-flush shadow-sm mb-5">
        <div class="card-header pt-6">
            <div class="card-title">
                <h3 class="fw-bold m-0">Public Building Filters</h3>
            </div>
        </div>
        <div class="card-body">
            <div class="row g-5">
                <div class="col-md-3">
                    <label class="form-label">Municipality</label>
                    <select id="filter_municipalitie" class="form-select form-select-solid public-building-select2" data-placeholder="Select municipality" data-allow-clear="true">
                        <option value=""></option>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $filterOptions['municipalities']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $municipality): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoop($loop->index); ?><?php endif; ?>
                            <option value="<?php echo e($municipality); ?>"><?php echo e($municipality); ?></option>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Neighborhood</label>
                    <select id="filter_neighborhood" class="form-select form-select-solid public-building-select2" data-placeholder="Select neighborhood" data-allow-clear="true">
                        <option value=""></option>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $filterOptions['neighborhoods']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $neighborhood): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoop($loop->index); ?><?php endif; ?>
                            <option value="<?php echo e($neighborhood); ?>"><?php echo e($neighborhood); ?></option>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                    </select>
                </div>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $filterGroups; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $groupName => $items): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoop($loop->index); ?><?php endif; ?>
                    <div class="col-md-3">
                        <label class="form-label"><?php echo e(str($groupName)->replace('_', ' ')->title()); ?></label>
                        <select id="filter_<?php echo e($groupName); ?>" class="form-select form-select-solid public-building-filter-select public-building-select2" data-filter-key="<?php echo e($groupName); ?>" data-placeholder="Select <?php echo e(str($groupName)->replace('_', ' ')->lower()); ?>" data-allow-clear="true">
                            <option value=""></option>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $items; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoop($loop->index); ?><?php endif; ?>
                                <option value="<?php echo e($item->name); ?>"><?php echo e($item->label); ?></option>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                        </select>
                    </div>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                <div class="col-md-3">
                    <label class="form-label">Researcher</label>
                    <select id="filter_assigned_to" class="form-select form-select-solid public-building-select2" data-placeholder="Select researcher" data-allow-clear="true">
                        <option value=""></option>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $filterOptions['researchers']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $researcher): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoop($loop->index); ?><?php endif; ?>
                            <option value="<?php echo e($researcher); ?>"><?php echo e($researcher); ?></option>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Search</label>
                    <input id="filter_search" type="text" class="form-control form-control-solid" placeholder="Building, municipality, objectid...">
                </div>
                <div class="col-md-3">
                    <label class="form-label">From Date</label>
                    <input id="filter_from_date" type="date" class="form-control form-control-solid" value="<?php echo e($filterOptions['min_damage_date']); ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">To Date</label>
                    <input id="filter_to_date" type="date" class="form-control form-control-solid" value="<?php echo e($filterOptions['max_damage_date']); ?>">
                </div>
            </div>
        </div>
    </div>

    <div class="card card-flush shadow-sm">
        <div class="card-header pt-6 d-flex justify-content-between align-items-center flex-wrap gap-3">
            <div class="card-title">
                <h3 class="fw-bold m-0">Public Building Surveys</h3>
            </div>
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <button type="button" class="btn btn-light-primary public-buildings-export" data-format="xlsx">Export Excel</button>
                <button type="button" class="btn btn-light-success public-buildings-export" data-format="csv">Export CSV</button>
                <button type="button" class="btn btn-light-danger public-buildings-export" data-format="pdf">Export PDF</button>
                <button type="button" id="reset_filters" class="btn btn-light">Reset Filters</button>
            </div>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table id="public_buildings_table" class="table table-row-bordered table-row-gray-100 align-middle gs-0 gy-3 w-100">
                    <thead>
                        <tr class="fw-bold text-muted bg-light">
                            <th>Object ID</th>
                            <th>Building Name</th>
                            <th>Municipality</th>
                            <th>Neighborhood</th>
                            <th>Damage Status</th>
                            <th>Date Of Damage</th>
                            <th>Units</th>
                            <th>Researcher</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                </table>
            </div>
        </div>
    </div>
<?php $__env->stopSection(); ?>

<?php $__env->startSection('script'); ?>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const exportRouteTemplate = <?php echo json_encode(route('public-buildings.export', ['format' => '__FORMAT__']), 512) ?>;

            $('.public-building-select2').each(function () {
                const placeholder = $(this).data('placeholder') || 'Select an option';
                const allowClear = String($(this).data('allow-clear')) === 'true';

                $(this).select2({
                    placeholder: placeholder,
                    allowClear: allowClear,
                    width: '100%'
                });
            });

            const initialQueryParams = new URLSearchParams(window.location.search);

            ['municipalitie', 'neighborhood', 'assigned_to'].forEach(function (key) {
                const value = initialQueryParams.get(key);

                if (value) {
                    $('#filter_' + key).val(value).trigger('change');
                }
            });

            $('.public-building-filter-select').each(function () {
                const key = $(this).data('filter-key');
                const value = initialQueryParams.get('filters[' + key + ']');

                if (value) {
                    $(this).val(value).trigger('change');
                }
            });

            $('#filter_search').val(initialQueryParams.get('search') || '');
            $('#filter_from_date').val(initialQueryParams.get('from_date') || $('#filter_from_date').val());
            $('#filter_to_date').val(initialQueryParams.get('to_date') || $('#filter_to_date').val());

            const dynamicFilters = function () {
                const filters = {};

                $('.public-building-filter-select').each(function () {
                    const key = $(this).data('filter-key');
                    const value = $(this).val();

                    if (value) {
                        filters[key] = value;
                    }
                });

                return filters;
            };

            const currentFilters = function () {
                const queryParams = new URLSearchParams(window.location.search);
                const filters = dynamicFilters();

                queryParams.forEach(function (value, key) {
                    if (key.startsWith('filters[') && key.endsWith(']')) {
                        filters[key.slice(8, -1)] = value;
                    }
                });

                return {
                    municipalitie: $('#filter_municipalitie').val() || queryParams.get('municipalitie'),
                    neighborhood: $('#filter_neighborhood').val() || queryParams.get('neighborhood'),
                    assigned_to: $('#filter_assigned_to').val() || queryParams.get('assigned_to'),
                    from_date: $('#filter_from_date').val() || queryParams.get('from_date'),
                    to_date: $('#filter_to_date').val() || queryParams.get('to_date'),
                    search: $('#filter_search').val() || queryParams.get('search'),
                    damaged_only: queryParams.get('damaged_only'),
                    with_units: queryParams.get('with_units'),
                    has_municipality: queryParams.get('has_municipality'),
                    has_neighborhood: queryParams.get('has_neighborhood'),
                    has_assigned_to: queryParams.get('has_assigned_to'),
                    occupied_only: queryParams.get('occupied_only'),
                    bodies_only: queryParams.get('bodies_only'),
                    uxo_only: queryParams.get('uxo_only'),
                    filters: filters,
                };
            };

            const table = $('#public_buildings_table').DataTable({
                processing: true,
                serverSide: true,
                ajax: {
                    url: '<?php echo e(route('public-buildings.data')); ?>',
                    data: function (d) {
                        const filters = currentFilters();
                        d.municipalitie = filters.municipalitie;
                        d.neighborhood = filters.neighborhood;
                        d.assigned_to = filters.assigned_to;
                        d.from_date = filters.from_date;
                        d.to_date = filters.to_date;
                        d.search = filters.search;
                        d.damaged_only = filters.damaged_only;
                        d.with_units = filters.with_units;
                        d.has_municipality = filters.has_municipality;
                        d.has_neighborhood = filters.has_neighborhood;
                        d.has_assigned_to = filters.has_assigned_to;
                        d.occupied_only = filters.occupied_only;
                        d.bodies_only = filters.bodies_only;
                        d.uxo_only = filters.uxo_only;
                        d.filters = filters.filters;
                    }
                },
                order: [[0, 'desc']],
                columns: [
                    { data: 'objectid', name: 'objectid' },
                    { data: 'building_name', name: 'building_name' },
                    { data: 'municipalitie', name: 'municipalitie' },
                    { data: 'neighborhood', name: 'neighborhood' },
                    { data: 'building_damage_status', name: 'building_damage_status', orderable: false, searchable: false },
                    { data: 'date_of_damage', name: 'date_of_damage' },
                    { data: 'units_count', name: 'units_count', searchable: false },
                    { data: 'assigned_to', name: 'assigned_to' },
                    { data: 'actions', name: 'actions', orderable: false, searchable: false, className: 'text-end' },
                ]
            });

            $('#filter_search').on('keyup', function () {
                table.search(this.value).draw();
            });

            $('#filter_municipalitie, #filter_neighborhood, #filter_assigned_to, #filter_from_date, #filter_to_date, .public-building-filter-select').on('change', function () {
                table.draw();
            });

            $('.public-buildings-export').on('click', function () {
                const format = $(this).data('format');
                const filters = currentFilters();
                const query = new URLSearchParams();

                ['municipalitie', 'neighborhood', 'assigned_to', 'from_date', 'to_date', 'search'].forEach(function (key) {
                    if (filters[key]) {
                        query.set(key, filters[key]);
                    }
                });

                Object.entries(filters.filters).forEach(function (entry) {
                    query.append('filters[' + entry[0] + ']', entry[1]);
                });

                window.location.href = exportRouteTemplate.replace('__FORMAT__', format) + '?' + query.toString();
            });

            $('#reset_filters').on('click', function () {
                $('#filter_search').val('');
                $('#filter_municipalitie').val(null).trigger('change');
                $('#filter_neighborhood').val(null).trigger('change');
                $('#filter_assigned_to').val(null).trigger('change');
                $('.public-building-filter-select').val(null).trigger('change');
                $('#filter_from_date').val('');
                $('#filter_to_date').val('');
                table.search('').draw();
            });
        });
    </script>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH D:\myProjects\phc\resources\views/PublicBuilding/index.blade.php ENDPATH**/ ?>