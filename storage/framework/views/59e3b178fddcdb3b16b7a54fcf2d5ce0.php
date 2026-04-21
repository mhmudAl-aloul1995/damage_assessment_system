<?php $__env->startSection('title', 'Road Facilities'); ?>
<?php $__env->startSection('pageName', 'Road Facilities'); ?>

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
                    <div class="text-muted fs-6 mb-2">Repeated Items</div>
                    <div class="fs-2hx fw-bold text-primary"><?php echo e($summary['total_items']); ?></div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card card-flush border border-gray-200 h-100">
                <div class="card-body text-center d-flex flex-column justify-content-center">
                    <div class="text-muted fs-6 mb-2">Damaged Roads</div>
                    <div class="fs-2hx fw-bold text-danger"><?php echo e($summary['damaged_roads']); ?></div>
                </div>
            </div>
        </div>
    </div>

    <div class="card card-flush shadow-sm mb-5">
        <div class="card-header pt-6">
            <div class="card-title">
                <h3 class="fw-bold m-0">Road Facilities Filters</h3>
            </div>
        </div>
        <div class="card-body">
            <div class="row g-5">
                <div class="col-md-3">
                    <label class="form-label">Municipality</label>
                    <select id="filter_municipalitie" class="form-select form-select-solid road-select2" data-placeholder="Select municipality" data-allow-clear="true">
                        <option value=""></option>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $filterOptions['municipalities']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $municipality): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoop($loop->index); ?><?php endif; ?>
                            <option value="<?php echo e($municipality); ?>"><?php echo e($municipality); ?></option>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Neighborhood</label>
                    <select id="filter_neighborhood" class="form-select form-select-solid road-select2" data-placeholder="Select neighborhood" data-allow-clear="true">
                        <option value=""></option>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $filterOptions['neighborhoods']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $neighborhood): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoop($loop->index); ?><?php endif; ?>
                            <option value="<?php echo e($neighborhood); ?>"><?php echo e($neighborhood); ?></option>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                    </select>
                </div>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $filterGroups; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $groupName => $items): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoop($loop->index); ?><?php endif; ?>
                    <div class="col-md-3">
                        <label class="form-label"><?php echo e(str($groupName)->replace('_', ' ')->title()); ?></label>
                        <select id="filter_<?php echo e($groupName); ?>" class="form-select form-select-solid road-filter-select road-select2" data-filter-key="<?php echo e($groupName); ?>" data-placeholder="Select <?php echo e(str($groupName)->replace('_', ' ')->lower()); ?>" data-allow-clear="true">
                            <option value=""></option>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $items; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoop($loop->index); ?><?php endif; ?>
                                <option value="<?php echo e($item->name); ?>"><?php echo e($item->label); ?></option>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                        </select>
                    </div>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                <div class="col-md-3">
                    <label class="form-label">Researcher</label>
                    <select id="filter_assigned_to" class="form-select form-select-solid road-select2" data-placeholder="Select researcher" data-allow-clear="true">
                        <option value=""></option>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $filterOptions['researchers']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $researcher): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoop($loop->index); ?><?php endif; ?>
                            <option value="<?php echo e($researcher); ?>"><?php echo e($researcher); ?></option>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Search</label>
                    <input id="filter_search" type="text" class="form-control form-control-solid" placeholder="Road, municipality, objectid...">
                </div>
                <div class="col-md-3">
                    <label class="form-label">From Date</label>
                    <input id="filter_from_date" type="date" class="form-control form-control-solid" value="<?php echo e($filterOptions['min_submission_date']); ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">To Date</label>
                    <input id="filter_to_date" type="date" class="form-control form-control-solid" value="<?php echo e($filterOptions['max_submission_date']); ?>">
                </div>
            </div>
        </div>
    </div>

    <div class="card card-flush shadow-sm">
        <div class="card-header pt-6 d-flex justify-content-between align-items-center flex-wrap gap-3">
            <div class="card-title">
                <h3 class="fw-bold m-0">Road Facilities Surveys</h3>
            </div>
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <button type="button" class="btn btn-light-primary road-facilities-export" data-format="xlsx">Export Excel</button>
                <button type="button" class="btn btn-light-success road-facilities-export" data-format="csv">Export CSV</button>
                <button type="button" class="btn btn-light-danger road-facilities-export" data-format="pdf">Export PDF</button>
                <button type="button" id="reset_filters" class="btn btn-light">Reset Filters</button>
            </div>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table id="road_facilities_table" class="table table-row-bordered table-row-gray-100 align-middle gs-0 gy-3 w-100">
                    <thead>
                        <tr class="fw-bold text-muted bg-light">
                            <th>Object ID</th>
                            <th>Road Name</th>
                            <th>Municipality</th>
                            <th>Neighborhood</th>
                            <th>Damage Level</th>
                            <th>Road Access</th>
                            <th>Submission Date</th>
                            <th>Items</th>
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
            const exportRouteTemplate = <?php echo json_encode(route('road-facilities.export', ['format' => '__FORMAT__']), 512) ?>;

            $('.road-select2').each(function () {
                const placeholder = $(this).data('placeholder') || 'Select an option';
                const allowClear = String($(this).data('allow-clear')) === 'true';

                $(this).select2({
                    placeholder: placeholder,
                    allowClear: allowClear,
                    width: '100%'
                });
            });

            const dynamicFilters = function () {
                const filters = {};

                $('.road-filter-select').each(function () {
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
                    with_items: queryParams.get('with_items'),
                    has_municipality: queryParams.get('has_municipality'),
                    has_neighborhood: queryParams.get('has_neighborhood'),
                    potholes_only: queryParams.get('potholes_only'),
                    obstacles_only: queryParams.get('obstacles_only'),
                    buried_bodies_only: queryParams.get('buried_bodies_only'),
                    uxo_only: queryParams.get('uxo_only'),
                    filters: filters,
                };
            };

            const table = $('#road_facilities_table').DataTable({
                processing: true,
                serverSide: true,
                ajax: {
                    url: '<?php echo e(route('road-facilities.data')); ?>',
                    data: function (d) {
                        const filters = currentFilters();
                        d.municipalitie = filters.municipalitie;
                        d.neighborhood = filters.neighborhood;
                        d.assigned_to = filters.assigned_to;
                        d.from_date = filters.from_date;
                        d.to_date = filters.to_date;
                        d.search = filters.search;
                        d.damaged_only = filters.damaged_only;
                        d.with_items = filters.with_items;
                        d.has_municipality = filters.has_municipality;
                        d.has_neighborhood = filters.has_neighborhood;
                        d.potholes_only = filters.potholes_only;
                        d.obstacles_only = filters.obstacles_only;
                        d.buried_bodies_only = filters.buried_bodies_only;
                        d.uxo_only = filters.uxo_only;
                        d.filters = filters.filters;
                    }
                },
                order: [[0, 'desc']],
                columns: [
                    { data: 'objectid', name: 'objectid' },
                    { data: 'str_name', name: 'str_name' },
                    { data: 'municipalitie', name: 'municipalitie' },
                    { data: 'neighborhood', name: 'neighborhood' },
                    { data: 'road_damage_level', name: 'road_damage_level', orderable: false, searchable: false },
                    { data: 'road_access', name: 'road_access' },
                    { data: 'submission_date', name: 'submission_date' },
                    { data: 'items_count', name: 'items_count', searchable: false },
                    { data: 'assigned_to', name: 'assigned_to' },
                    { data: 'actions', name: 'actions', orderable: false, searchable: false, className: 'text-end' },
                ]
            });

            $('#filter_search').on('keyup', function () {
                table.search(this.value).draw();
            });

            $('#filter_municipalitie, #filter_neighborhood, #filter_assigned_to, #filter_from_date, #filter_to_date, .road-filter-select').on('change', function () {
                table.draw();
            });

            $('.road-facilities-export').on('click', function () {
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
                $('.road-filter-select').val(null).trigger('change');
                $('#filter_from_date').val('');
                $('#filter_to_date').val('');
                table.search('').draw();
            });
        });
    </script>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH D:\myProjects\phc\resources\views/RoadFacility/index.blade.php ENDPATH**/ ?>