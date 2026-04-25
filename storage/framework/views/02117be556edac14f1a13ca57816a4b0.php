<?php $__env->startSection('title', __('multilingual.field_engineer_report.title')); ?>
<?php $__env->startSection('pageName', __('multilingual.field_engineer_report.page_name')); ?>

<?php
    $isArabic = app()->getLocale() === 'ar';
    $currentTab = request('tab', 'buildings');
    $summaryCards = [
        ['key' => 'total_buildings', 'class' => 'primary'],
        ['key' => 'total_housing_units', 'class' => 'info'],
        ['key' => 'damaged_buildings', 'class' => 'danger'],
        ['key' => 'damaged_housing_units', 'class' => 'warning'],
        ['key' => 'building_edits', 'class' => 'success'],
        ['key' => 'housing_edits', 'class' => 'dark'],
        ['key' => 'accepted_statuses', 'class' => 'success'],
        ['key' => 'rejected_statuses', 'class' => 'danger'],
        ['key' => 'need_review_statuses', 'class' => 'warning'],
        ['key' => 'last_updated_at', 'class' => 'secondary', 'isDate' => true],
        ['key' => 'completion_rate', 'class' => 'primary', 'isPercent' => true],
        ['key' => 'completed_buildings', 'class' => 'success'],
        ['key' => 'not_completed_buildings', 'class' => 'danger'],
    ];
?>

<?php $__env->startSection('content'); ?>
    <style>
        .field-engineer-report .stats-card {
            border: 1px dashed #d9dee7;
            border-radius: 1rem;
            padding: 1.25rem;
            height: 100%;
            background: #fff;
        }

        .field-engineer-report .stats-card .value {
            font-size: 1.8rem;
            font-weight: 700;
            line-height: 1.2;
        }

        .field-engineer-report .toolbar-actions .btn {
            min-width: 120px;
        }

        @media print {
            body * {
                visibility: hidden;
            }

            .field-engineer-report .print-target,
            .field-engineer-report .print-target * {
                visibility: visible;
            }

            .field-engineer-report .print-target {
                position: absolute;
                inset: 0;
                width: 100%;
                padding: 20px;
            }

            .field-engineer-report .nav,
            .field-engineer-report .toolbar-actions,
            .field-engineer-report .card-header,
            .field-engineer-report .dataTables_length,
            .field-engineer-report .dataTables_filter,
            .field-engineer-report .dataTables_paginate,
            .field-engineer-report .dataTables_info {
                display: none !important;
            }
        }
    </style>

    <div class="field-engineer-report">
        <div class="card mb-7">
            <div class="card-body d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-5">
                <div>
                    <div class="text-muted fs-7 mb-2">
                        <?php echo e(__('menu.reports.title')); ?> /
                        <?php echo e(__('multilingual.field_engineer_report.page_name')); ?>

                    </div>
                    <h2 class="mb-2"><?php echo e(__('multilingual.field_engineer_report.title')); ?></h2>
                    <div class="text-muted fs-6">
                        <?php echo e(__('multilingual.field_engineer_report.subtitle')); ?>

                    </div>
                    <div class="mt-3 text-gray-700 fw-semibold">
                        <?php echo e(__('multilingual.field_engineer_report.results_for')); ?>:
                        <span class="badge badge-light-primary fs-7">
                            <?php echo e($filters['assignedto'] ?: __('multilingual.field_engineer_report.no_engineer_selected')); ?>

                        </span>
                    </div>
                </div>

                <div class="toolbar-actions d-flex flex-wrap gap-3">
                    <a href="<?php echo e(route('reports.field-engineer.index', request()->query())); ?>" class="btn btn-light-primary">
                        <i class="ki-duotone ki-arrows-circle fs-2">
                            <span class="path1"></span>
                            <span class="path2"></span>
                        </i>
                        <?php echo e(__('multilingual.field_engineer_report.actions.refresh')); ?>

                    </a>

                    <button type="button" class="btn btn-light-success export-tab-btn" data-format="xlsx">
                        <i class="ki-duotone ki-file-down fs-2">
                            <span class="path1"></span>
                            <span class="path2"></span>
                        </i>
                        <?php echo e(__('multilingual.field_engineer_report.actions.export_excel')); ?>

                    </button>

                    <button type="button" class="btn btn-light-info export-tab-btn" data-format="csv">
                        <i class="ki-duotone ki-file-down fs-2">
                            <span class="path1"></span>
                            <span class="path2"></span>
                        </i>
                        <?php echo e(__('multilingual.field_engineer_report.actions.export_csv')); ?>

                    </button>

                    <button type="button" class="btn btn-light-dark" id="printActiveTab">
                        <i class="ki-duotone ki-printer fs-2">
                            <span class="path1"></span>
                            <span class="path2"></span>
                        </i>
                        <?php echo e(__('multilingual.field_engineer_report.actions.print')); ?>

                    </button>
                </div>
            </div>
        </div>

        <div class="card mb-7">
            <div class="card-header border-0 pt-6">
                <div class="card-title">
                    <h3 class="mb-0"><?php echo e(__('multilingual.field_engineer_report.filters_title')); ?></h3>
                </div>
                <div class="card-toolbar">
                    <button class="btn btn-sm btn-light-primary" type="button" data-bs-toggle="collapse"
                        data-bs-target="#fieldEngineerFilters" aria-expanded="true" id="toggleFieldEngineerFilters">
                        <i class="fas fa-chevron-down me-1"></i>
                        <?php echo e(__('multilingual.field_engineer_report.actions.hide_filters')); ?>

                    </button>
                </div>
            </div>
            <div class="collapse show" id="fieldEngineerFilters">
                <div class="card-body pt-2">
                    <form method="GET" action="<?php echo e(route('reports.field-engineer.index')); ?>" id="fieldEngineerFiltersForm">
                        <div class="row g-5">
                            <div class="col-md-4">
                                <label class="form-label"><?php echo e(__('multilingual.field_engineer_report.filters.assignedto')); ?></label>
                                <select name="assignedto" class="form-select form-select-solid report-select2"
                                    data-placeholder="<?php echo e(__('multilingual.field_engineer_report.select_placeholder')); ?>">
                                    <option value=""><?php echo e(__('multilingual.field_engineer_report.all_options')); ?></option>
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $filterOptions['engineers']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $engineer): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoop($loop->index); ?><?php endif; ?>
                                        <option value="<?php echo e($engineer); ?>" <?php if($filters['assignedto'] === $engineer): echo 'selected'; endif; ?>><?php echo e($engineer); ?></option>
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label"><?php echo e(__('multilingual.field_engineer_report.filters.municipalitie')); ?></label>
                                <select name="municipalitie" class="form-select form-select-solid report-select2"
                                    data-placeholder="<?php echo e(__('multilingual.field_engineer_report.select_placeholder')); ?>">
                                    <option value=""><?php echo e(__('multilingual.field_engineer_report.all_options')); ?></option>
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $filterOptions['municipalities']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $municipality): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoop($loop->index); ?><?php endif; ?>
                                        <option value="<?php echo e($municipality); ?>" <?php if($filters['municipalitie'] === $municipality): echo 'selected'; endif; ?>><?php echo e($municipality); ?></option>
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label"><?php echo e(__('multilingual.field_engineer_report.filters.neighborhood')); ?></label>
                                <select name="neighborhood" class="form-select form-select-solid report-select2"
                                    data-placeholder="<?php echo e(__('multilingual.field_engineer_report.select_placeholder')); ?>">
                                    <option value=""><?php echo e(__('multilingual.field_engineer_report.all_options')); ?></option>
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $filterOptions['neighborhoods']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $neighborhood): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoop($loop->index); ?><?php endif; ?>
                                        <option value="<?php echo e($neighborhood); ?>" <?php if($filters['neighborhood'] === $neighborhood): echo 'selected'; endif; ?>><?php echo e($neighborhood); ?></option>
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label"><?php echo e(__('multilingual.field_engineer_report.filters.building_damage_status')); ?></label>
                                <select name="building_damage_status" class="form-select form-select-solid report-select2"
                                    data-placeholder="<?php echo e(__('multilingual.field_engineer_report.select_placeholder')); ?>">
                                    <option value=""><?php echo e(__('multilingual.field_engineer_report.all_options')); ?></option>
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $filterOptions['building_damage_statuses']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $status): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoop($loop->index); ?><?php endif; ?>
                                        <option value="<?php echo e($status); ?>" <?php if($filters['building_damage_status'] === $status): echo 'selected'; endif; ?>><?php echo e($status); ?></option>
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label"><?php echo e(__('multilingual.field_engineer_report.filters.engineer_status')); ?></label>
                                <select name="engineer_status" class="form-select form-select-solid report-select2"
                                    data-placeholder="<?php echo e(__('multilingual.field_engineer_report.select_placeholder')); ?>">
                                    <option value=""><?php echo e(__('multilingual.field_engineer_report.all_options')); ?></option>
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $filterOptions['engineer_statuses']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $status): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoop($loop->index); ?><?php endif; ?>
                                        <option value="<?php echo e($status['name']); ?>" <?php if($filters['engineer_status'] === $status['name']): echo 'selected'; endif; ?>><?php echo e($status['label']); ?></option>
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label"><?php echo e(__('multilingual.field_engineer_report.filters.legal_status')); ?></label>
                                <select name="legal_status" class="form-select form-select-solid report-select2"
                                    data-placeholder="<?php echo e(__('multilingual.field_engineer_report.select_placeholder')); ?>">
                                    <option value=""><?php echo e(__('multilingual.field_engineer_report.all_options')); ?></option>
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $filterOptions['legal_statuses']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $status): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoop($loop->index); ?><?php endif; ?>
                                        <option value="<?php echo e($status['name']); ?>" <?php if($filters['legal_status'] === $status['name']): echo 'selected'; endif; ?>><?php echo e($status['label']); ?></option>
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label"><?php echo e(__('multilingual.field_engineer_report.filters.final_status')); ?></label>
                                <select name="final_status" class="form-select form-select-solid report-select2"
                                    data-placeholder="<?php echo e(__('multilingual.field_engineer_report.select_placeholder')); ?>">
                                    <option value=""><?php echo e(__('multilingual.field_engineer_report.all_options')); ?></option>
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $filterOptions['final_statuses']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $status): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoop($loop->index); ?><?php endif; ?>
                                        <option value="<?php echo e($status['name']); ?>" <?php if($filters['final_status'] === $status['name']): echo 'selected'; endif; ?>><?php echo e($status['label']); ?></option>
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label"><?php echo e(__('multilingual.field_engineer_report.filters.from_date')); ?></label>
                                <input type="date" name="from_date" value="<?php echo e($filters['from_date']); ?>"
                                    class="form-control form-control-solid">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label"><?php echo e(__('multilingual.field_engineer_report.filters.to_date')); ?></label>
                                <input type="date" name="to_date" value="<?php echo e($filters['to_date']); ?>"
                                    class="form-control form-control-solid">
                            </div>
                            <div class="col-md-8">
                                <label class="form-label"><?php echo e(__('multilingual.field_engineer_report.filters.search')); ?></label>
                                <input type="text" name="search" value="<?php echo e($filters['search']); ?>"
                                    placeholder="<?php echo e(__('multilingual.field_engineer_report.search_placeholder')); ?>"
                                    class="form-control form-control-solid">
                            </div>
                            <div class="col-md-4 d-flex align-items-end gap-3">
                                <button type="submit" class="btn btn-primary flex-fill">
                                    <?php echo e(__('multilingual.field_engineer_report.actions.apply_filters')); ?>

                                </button>
                                <a href="<?php echo e(route('reports.field-engineer.index')); ?>" class="btn btn-light flex-fill">
                                    <?php echo e(__('multilingual.field_engineer_report.actions.reset')); ?>

                                </a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="row g-5 mb-7">
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $summaryCards; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $card): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoop($loop->index); ?><?php endif; ?>
                <div class="col-md-3 col-sm-6">
                    <div class="stats-card">
                        <div class="text-muted fw-semibold fs-7 mb-3">
                            <?php echo e(__("multilingual.field_engineer_report.stats.{$card['key']}")); ?>

                        </div>
                        <div class="value text-<?php echo e($card['class']); ?>">
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!empty($card['isDate'])): ?>
                                <?php echo e($summary[$card['key']] ? \Illuminate\Support\Carbon::parse($summary[$card['key']])->format('Y-m-d h:i A') : '-'); ?>

                            <?php elseif(!empty($card['isPercent'])): ?>
                                <?php echo e(number_format((float) $summary[$card['key']], 1)); ?>%
                            <?php else: ?>
                                <?php echo e(number_format((int) $summary[$card['key']])); ?>

                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </div>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($card['key'] === 'completion_rate'): ?>
                            <div class="progress h-8px mt-4">
                                <div class="progress-bar bg-primary" role="progressbar"
                                    style="width: <?php echo e(min(100, (float) $summary[$card['key']])); ?>%;"></div>
                            </div>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </div>
                </div>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
        </div>

        <div class="card">
            <div class="card-body">
                <ul class="nav nav-tabs nav-line-tabs nav-line-tabs-2x fs-6 mb-5">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = ['buildings', 'housing_units', 'edits', 'status_history', 'assignments']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $tab): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoop($loop->index); ?><?php endif; ?>
                        <li class="nav-item">
                            <a class="nav-link <?php echo e($currentTab === $tab ? 'active' : ''); ?>" data-bs-toggle="tab"
                                href="#tab-<?php echo e($tab); ?>" data-tab="<?php echo e($tab); ?>">
                                <?php echo e(__("multilingual.field_engineer_report.tabs.{$tab}")); ?>

                            </a>
                        </li>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                </ul>

                <div class="tab-content">
                    <div class="tab-pane fade <?php echo e($currentTab === 'buildings' ? 'show active' : ''); ?>" id="tab-buildings">
                        <div class="table-responsive print-target">
                            <table class="table table-row-bordered table-striped gy-5 align-middle w-100 report-datatable" id="fieldEngineerBuildingsTable">
                                <thead>
                                    <tr class="fw-bold text-uppercase gs-0">
                                        <th><?php echo e(__('multilingual.field_engineer_report.columns.object_id')); ?></th>
                                        <th><?php echo e(__('multilingual.field_engineer_report.columns.globalid')); ?></th>
                                        <th><?php echo e(__('multilingual.field_engineer_report.columns.assignedto')); ?></th>
                                        <th><?php echo e(__('multilingual.field_engineer_report.columns.municipality')); ?></th>
                                        <th><?php echo e(__('multilingual.field_engineer_report.columns.neighborhood')); ?></th>
                                        <th><?php echo e(__('multilingual.field_engineer_report.columns.parcel_number')); ?></th>
                                        <th><?php echo e(__('multilingual.field_engineer_report.columns.building_use')); ?></th>
                                        <th><?php echo e(__('multilingual.field_engineer_report.columns.building_damage_status')); ?></th>
                                        <th><?php echo e(__('multilingual.field_engineer_report.columns.creationdate')); ?></th>
                                        <th><?php echo e(__('multilingual.field_engineer_report.columns.last_update')); ?></th>
                                        <th><?php echo e(__('multilingual.field_engineer_report.columns.final_status')); ?></th>
                                    </tr>
                                </thead>
                            </table>
                        </div>
                    </div>

                    <div class="tab-pane fade <?php echo e($currentTab === 'housing_units' ? 'show active' : ''); ?>" id="tab-housing_units">
                        <div class="table-responsive print-target">
                            <table class="table table-row-bordered table-striped gy-5 align-middle w-100 report-datatable" id="fieldEngineerHousingTable">
                                <thead>
                                    <tr class="fw-bold text-uppercase gs-0">
                                        <th><?php echo e(__('multilingual.field_engineer_report.columns.object_id')); ?></th>
                                        <th><?php echo e(__('multilingual.field_engineer_report.columns.parentglobalid')); ?></th>
                                        <th><?php echo e(__('multilingual.field_engineer_report.columns.building_number')); ?></th>
                                        <th><?php echo e(__('multilingual.field_engineer_report.columns.unit_use')); ?></th>
                                        <th><?php echo e(__('multilingual.field_engineer_report.columns.damage_status')); ?></th>
                                        <th><?php echo e(__('multilingual.field_engineer_report.columns.occupant_status')); ?></th>
                                        <th><?php echo e(__('multilingual.field_engineer_report.columns.creationdate')); ?></th>
                                    </tr>
                                </thead>
                            </table>
                        </div>
                    </div>

                    <div class="tab-pane fade <?php echo e($currentTab === 'edits' ? 'show active' : ''); ?>" id="tab-edits">
                        <div class="table-responsive print-target">
                            <table class="table table-row-bordered table-striped gy-5 align-middle w-100 report-datatable" id="fieldEngineerEditsTable">
                                <thead>
                                    <tr class="fw-bold text-uppercase gs-0">
                                        <th><?php echo e(__('multilingual.field_engineer_report.columns.type')); ?></th>
                                        <th><?php echo e(__('multilingual.field_engineer_report.columns.globalid')); ?></th>
                                        <th><?php echo e(__('multilingual.field_engineer_report.columns.field_name')); ?></th>
                                        <th><?php echo e(__('multilingual.field_engineer_report.columns.old_value')); ?></th>
                                        <th><?php echo e(__('multilingual.field_engineer_report.columns.new_value')); ?></th>
                                        <th><?php echo e(__('multilingual.field_engineer_report.columns.updated_by')); ?></th>
                                        <th><?php echo e(__('multilingual.field_engineer_report.columns.updated_at')); ?></th>
                                    </tr>
                                </thead>
                            </table>
                        </div>
                    </div>

                    <div class="tab-pane fade <?php echo e($currentTab === 'status_history' ? 'show active' : ''); ?>" id="tab-status_history">
                        <div class="table-responsive print-target">
                            <table class="table table-row-bordered table-striped gy-5 align-middle w-100 report-datatable" id="fieldEngineerStatusHistoryTable">
                                <thead>
                                    <tr class="fw-bold text-uppercase gs-0">
                                        <th><?php echo e(__('multilingual.field_engineer_report.columns.type')); ?></th>
                                        <th><?php echo e(__('multilingual.field_engineer_report.columns.item_number')); ?></th>
                                        <th><?php echo e(__('multilingual.field_engineer_report.columns.status')); ?></th>
                                        <th><?php echo e(__('multilingual.field_engineer_report.columns.changed_by')); ?></th>
                                        <th><?php echo e(__('multilingual.field_engineer_report.columns.changed_at')); ?></th>
                                    </tr>
                                </thead>
                            </table>
                        </div>
                    </div>

                    <div class="tab-pane fade <?php echo e($currentTab === 'assignments' ? 'show active' : ''); ?>" id="tab-assignments">
                        <div class="table-responsive print-target">
                            <table class="table table-row-bordered table-striped gy-5 align-middle w-100 report-datatable" id="fieldEngineerAssignmentsTable">
                                <thead>
                                    <tr class="fw-bold text-uppercase gs-0">
                                        <th><?php echo e(__('multilingual.field_engineer_report.columns.building_id')); ?></th>
                                        <th><?php echo e(__('multilingual.field_engineer_report.columns.assigned_user')); ?></th>
                                        <th><?php echo e(__('multilingual.field_engineer_report.columns.assigned_by')); ?></th>
                                        <th><?php echo e(__('multilingual.field_engineer_report.columns.assigned_date')); ?></th>
                                        <th><?php echo e(__('multilingual.field_engineer_report.columns.notes')); ?></th>
                                    </tr>
                                </thead>
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
        document.addEventListener('DOMContentLoaded', function () {
            const localeIsArabic = <?php echo json_encode($isArabic, 15, 512) ?>;
            const dataTablesLanguageUrl = localeIsArabic
                ? '//cdn.datatables.net/plug-ins/1.13.4/i18n/ar.json'
                : '//cdn.datatables.net/plug-ins/1.13.4/i18n/en-GB.json';
            const filtersForm = document.getElementById('fieldEngineerFiltersForm');
            const currentTabInputValue = <?php echo json_encode($currentTab, 15, 512) ?>;
            const tables = {};

            $('.report-select2').select2({
                allowClear: true,
                width: '100%',
                dir: localeIsArabic ? 'rtl' : 'ltr',
            });

            const toggleButton = document.getElementById('toggleFieldEngineerFilters');
            const collapseElement = document.getElementById('fieldEngineerFilters');

            collapseElement.addEventListener('shown.bs.collapse', function () {
                toggleButton.innerHTML = '<i class="fas fa-chevron-down me-1"></i> <?php echo e(__('multilingual.field_engineer_report.actions.hide_filters')); ?>';
            });

            collapseElement.addEventListener('hidden.bs.collapse', function () {
                toggleButton.innerHTML = '<i class="fas fa-chevron-left me-1"></i> <?php echo e(__('multilingual.field_engineer_report.actions.show_filters')); ?>';
            });

            function filterPayload() {
                return $(filtersForm).serializeArray().reduce(function (carry, item) {
                    carry[item.name] = item.value;
                    return carry;
                }, {});
            }

            function buildExportUrl(format) {
                const activeTab = $('.nav-link.active').data('tab') || currentTabInputValue;
                const params = new URLSearchParams(filterPayload());
                params.set('tab', activeTab);

                return "<?php echo e(route('reports.field-engineer.export', ['tab' => '__TAB__', 'format' => '__FORMAT__'])); ?>"
                    .replace('__TAB__', activeTab)
                    .replace('__FORMAT__', format) + '?' + params.toString();
            }

            function initializeDataTable(key, selector, ajaxUrl, columns) {
                if (tables[key]) {
                    return tables[key];
                }

                tables[key] = $(selector).DataTable({
                    processing: true,
                    serverSide: true,
                    responsive: true,
                    searchDelay: 500,
                    pageLength: 25,
                    order: [[0, 'desc']],
                    ajax: {
                        url: ajaxUrl,
                        data: function (data) {
                            Object.assign(data, filterPayload());
                        }
                    },
                    columns: columns,
                    language: {
                        url: dataTablesLanguageUrl
                    }
                });

                return tables[key];
            }

            const tabTables = {
                buildings: function () {
                    return tables.buildings;
                },
                housing_units: function () {
                    return initializeDataTable('housing_units', '#fieldEngineerHousingTable', "<?php echo e(route('reports.field-engineer.housing-units')); ?>", [
                        {data: 'objectid', name: 'housing_units.objectid'},
                        {data: 'parentglobalid', name: 'housing_units.parentglobalid'},
                        {data: 'building_objectid', name: 'buildings.objectid'},
                        {data: 'housing_unit_type', name: 'housing_unit_type'},
                        {data: 'unit_damage_status', name: 'unit_damage_status'},
                        {data: 'occupied', name: 'occupied'},
                        {data: 'creationdate', name: 'housing_units.creationdate'},
                    ]);
                },
                edits: function () {
                    return initializeDataTable('edits', '#fieldEngineerEditsTable', "<?php echo e(route('reports.field-engineer.edits')); ?>", [
                        {data: 'source_type', name: 'edit_assessments.type'},
                        {data: 'global_id', name: 'edit_assessments.global_id'},
                        {data: 'field_name', name: 'edit_assessments.field_name'},
                        {data: 'old_value', name: 'old_value', orderable: false},
                        {data: 'new_value', name: 'edit_assessments.field_value'},
                        {data: 'updated_by', name: 'users.name'},
                        {data: 'updated_at', name: 'edit_assessments.updated_at'},
                    ]);
                },
                status_history: function () {
                    return initializeDataTable('status_history', '#fieldEngineerStatusHistoryTable', "<?php echo e(route('reports.field-engineer.status-history')); ?>", [
                        {data: 'item_type', name: 'status_history.item_type'},
                        {data: 'item_number', name: 'status_history.item_number'},
                        {data: 'status_label', name: 'status_history.status_label', orderable: false},
                        {data: 'changed_by', name: 'status_history.changed_by'},
                        {data: 'created_at', name: 'status_history.created_at'},
                    ]);
                },
                assignments: function () {
                    return initializeDataTable('assignments', '#fieldEngineerAssignmentsTable', "<?php echo e(route('reports.field-engineer.assignments')); ?>", [
                        {data: 'building_id', name: 'assigned_assessment_users.building_id'},
                        {data: 'assigned_user', name: 'assigned_user.name'},
                        {data: 'assigned_by', name: 'manager_user.name'},
                        {data: 'assigned_date', name: 'assigned_assessment_users.created_at'},
                        {data: 'notes', name: 'notes', orderable: false},
                    ]);
                },
            };

            initializeDataTable('buildings', '#fieldEngineerBuildingsTable', "<?php echo e(route('reports.field-engineer.buildings')); ?>", [
                {data: 'objectid', name: 'buildings.objectid'},
                {data: 'globalid', name: 'buildings.globalid'},
                {data: 'assignedto', name: 'buildings.assignedto'},
                {data: 'municipalitie', name: 'municipalitie'},
                {data: 'neighborhood', name: 'neighborhood'},
                {data: 'parcel_no1', name: 'buildings.parcel_no1'},
                {data: 'building_use', name: 'building_use'},
                {data: 'building_damage_status', name: 'building_damage_status'},
                {data: 'creationdate', name: 'buildings.creationdate'},
                {data: 'editdate', name: 'buildings.editdate'},
                {data: 'final_status_label', name: 'final_statuses.status_label', orderable: false},
            ]);

            $('a[data-bs-toggle="tab"]').on('shown.bs.tab', function (event) {
                const tab = $(event.target).data('tab');
                if (tabTables[tab]) {
                    tabTables[tab]();
                }

                const params = new URLSearchParams(window.location.search);
                params.set('tab', tab);
                window.history.replaceState({}, '', window.location.pathname + '?' + params.toString());
            });

            if (currentTabInputValue !== 'buildings') {
                tabTables[currentTabInputValue]();
            }

            $('.export-tab-btn').on('click', function () {
                window.location.href = buildExportUrl($(this).data('format'));
            });

            $('#printActiveTab').on('click', function () {
                window.print();
            });
        });
    </script>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH D:\myProjects\phc\resources\views/reports/field-engineer/index.blade.php ENDPATH**/ ?>