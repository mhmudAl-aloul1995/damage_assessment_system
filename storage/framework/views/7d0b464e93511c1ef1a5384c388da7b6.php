<?php $__env->startSection('content'); ?>
	<style>
		.card-toolbar .dropdown-menu .dropdown-item {
			font-size: 13px;
			padding: 0.65rem 1rem;
			transition: 0.2s ease;
		}

		.card-toolbar .dropdown-menu .dropdown-item:hover {
			background-color: #f8f9fa;
		}
	</style>
	<div class="container py-4">

		<div class="card shadow-sm">
			<div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
				<h3 class="mb-0">تصدير بيانات المباني والوحدات السكنية</h3>


			</div>

			<div class="card-body">
				<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(session('error')): ?>
					<div class="alert alert-danger">
						<?php echo e(session('error')); ?>

					</div>
				<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

				<form id="exportForm" method="POST">
					<?php echo csrf_field(); ?>

					
					<div class="card card-bordered mb-5">
						<div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
							<h3 class="card-title mb-0">الفلاتر</h3>

							<div class="d-flex gap-2">
								<button class="btn btn-sm btn-light-primary" type="button" data-bs-toggle="collapse"
									data-bs-target="#filtersCollapse" aria-expanded="true" aria-controls="filtersCollapse"
									id="toggleFiltersBtn">
									<i class="fas fa-chevron-down me-1"></i>
									إظهار
								</button>

								<button type="button" class="btn btn-sm btn-light-danger" onclick="resetFilters()">
									<i class="fas fa-times me-1"></i>
									مسح الفلاتر
								</button>
								<button type="button"
									class="btn btn-light-primary btn-sm dropdown-toggle d-flex align-items-center gap-1"
									data-bs-toggle="dropdown" aria-expanded="false">

									<i class="ki-duotone ki-exit-down fs-5">
										<span class="path1"></span>
										<span class="path2"></span>
									</i>
									تصدير
								</button>

								<div class="dropdown-menu dropdown-menu-end shadow-sm border-0">
									<button class="dropdown-item d-flex align-items-center gap-2 export-btn" type="button"
										data-type="excel">
										<i class="ki-duotone ki-file-down fs-4 text-success">
											<span class="path1"></span>
											<span class="path2"></span>
										</i>
										<span>Excel (.xlsx)</span>
									</button>

									<button class="dropdown-item d-flex align-items-center gap-2 export-btn" type="button"
										data-type="pdf">
										<i class="ki-duotone ki-file-down fs-4 text-danger">
											<span class="path1"></span>
											<span class="path2"></span>
										</i>
										<span>PDF (.pdf)</span>
									</button>
								</div>
							</div>

						</div>

						<div class="collapse" id="filtersCollapse">
							<div class="card-body">

								<div class="mb-5">
									<div class="input-group">
										<span class="input-group-text">
											<i class="fas fa-search"></i>
										</span>

										<input type="text" id="filterSearch" class="form-control form-control-solid"
											placeholder="ابحث عن الفلتر... مثل neighborhood أو locality"
											onkeyup="filterFilterCards()">

										<button type="button" class="btn btn-light" onclick="clearFilterSearch()">
											مسح
										</button>
									</div>

									<div class="text-muted fs-7 mt-2">
										عدد الفلاتر الظاهرة:
										<span id="filterCardsCounter"><?php echo e(count($filters)); ?></span>
										/ <?php echo e(count($filters)); ?>

									</div>
								</div>

								<div class="row" id="filtersCardsList">
									<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $filters; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $listName => $items): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoop($loop->index); ?><?php endif; ?>
										<div class="col-md-4 mb-4 filter-card-item">
											<label class="form-label fw-bold searchable-filter-name">
												<?php echo e($assessmentLabels[$listName] ?? ucwords(str_replace('_', ' ', $listName))); ?>

											</label>

											<select name="filters[<?php echo e($listName); ?>][]"
												class="form-select form-select-solid filter-select2" multiple
												data-placeholder="اختر <?php echo e($assessmentLabels[$listName] ?? ucwords(str_replace('_', ' ', $listName))); ?>">
												<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $items; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoop($loop->index); ?><?php endif; ?>
													<option value="<?php echo e($item->name); ?>">
														<?php echo e($item->label ?? $item->name); ?>

													</option>
												<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
											</select>
										</div>
									<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>

									<div class="col-md-4 mb-4 filter-card-item static-filter-card">
										<label class="form-label fw-bold searchable-filter-name">عدد أفراد الأسرة من</label>
										<input type="number" name="family_members_from"
											class="form-control form-control-solid" min="0"
											placeholder="عدد أفراد الأسرة من" value="<?php echo e(old('family_members_from')); ?>">
									</div>

									<div class="col-md-4 mb-4 filter-card-item static-filter-card">
										<label class="form-label fw-bold searchable-filter-name">عدد أفراد الأسرة
											إلى</label>
										<input type="number" name="family_members_to" placeholder="عدد أفراد الأسرة إلى"
											class="form-control form-control-solid" min="0"
											value="<?php echo e(old('family_members_to')); ?>">
									</div>
								</div>
							</div>
						</div>
					</div>

					<div class="row">
						

						<div class="col-lg-6 mb-4">
							<div class="card card-bordered h-100">
								<div class="card-header border-0 pt-4">
									<div class="card-title">
										<h4 class="mb-0">حقول جدول المبنى</h4>
									</div>

									<div class="card-toolbar d-flex gap-2">
										<button type="button" class="btn btn-sm btn-primary"
											onclick="toggleVisibleGroup('buildingColumnsList', 'building_columns[]', true)">
											تحديد الكل
										</button>

										<button type="button" class="btn btn-sm btn-light-danger"
											onclick="toggleVisibleGroup('buildingColumnsList', 'building_columns[]', false)">
											إلغاء الكل
										</button>
									</div>
								</div>

								<div class="card-body pt-2">
									<div class="mb-4">
										<div class="input-group">
											<span class="input-group-text">
												<i class="fas fa-search"></i>
											</span>

											<input type="text" id="buildingSearch" class="form-control form-control-solid"
												placeholder="ابحث بالاسم أو الـ label أو الـ hint..."
												onkeyup="filterColumns('buildingSearch', 'buildingColumnsList', 'buildingCounter')">

											<button type="button" class="btn btn-light"
												onclick="clearSearch('buildingSearch', 'buildingColumnsList', 'buildingCounter')">
												مسح
											</button>
										</div>

										<div class="text-muted fs-7 mt-2">
											عدد النتائج الكلية:
											<span id="buildingCounter"><?php echo e(count($buildingColumns)); ?></span>
											/ <?php echo e(count($buildingColumns)); ?>

										</div>
									</div>

									<div class="row" id="buildingColumnsList">
										<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $buildingColumns; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $column): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoop($loop->index); ?><?php endif; ?>
											<div class="col-md-6 mb-3 column-item">
												<label
													class="form-check form-check-custom form-check-solid align-items-start w-100 border rounded p-3">
													<input class="form-check-input mt-1" type="checkbox"
														name="building_columns[]" value="<?php echo e($column); ?>">

													<span class="form-check-label ms-3 w-100">
														<strong class="d-block fs-6 searchable-label">
															<?php echo e($assessmentMeta[$column]['label'] ?? ucwords(str_replace('_', ' ', $column))); ?>

														</strong>

														<small class="text-muted d-block mt-1 searchable-column">
															<?php echo e($assessmentMeta[$column]['hint'] ?? ''); ?>

														</small>

														<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!empty($assessmentMeta[$column]['hint']) && trim($assessmentMeta[$column]['hint']) !== ''): ?>
															<small class="text-info d-block mt-2 searchable-hint">
																<?php echo e($column); ?>

															</small>
														<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
													</span>
												</label>
											</div>
										<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
											<div class="col-12">
												<div class="alert alert-warning mb-0">
													لا توجد حقول متاحة في جدول buildings
												</div>
											</div>
										<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
									</div>
								</div>
							</div>
						</div>

						
						<div class="col-lg-6 mb-4">
							<div class="card card-bordered h-100">
								<div class="card-header border-0 pt-4">
									<div class="card-title">
										<h4 class="mb-0">حقول جدول الوحد السكانية</h4>
									</div>

									<div class="card-toolbar d-flex gap-2">
										<button type="button" class="btn btn-sm btn-primary"
											onclick="toggleVisibleGroup('housingColumnsList', 'housing_columns[]', true)">
											تحديد الكل
										</button>

										<button type="button" class="btn btn-sm btn-light-danger"
											onclick="toggleVisibleGroup('housingColumnsList', 'housing_columns[]', false)">
											إلغاء الكل
										</button>
									</div>
								</div>

								<div class="card-body pt-2">
									<div class="mb-4">
										<div class="input-group">
											<span class="input-group-text">
												<i class="fas fa-search"></i>
											</span>

											<input type="text" id="housingSearch" class="form-control form-control-solid"
												placeholder="ابحث بالاسم أو الـ label أو الـ hint..."
												onkeyup="filterColumns('housingSearch', 'housingColumnsList', 'housingCounter')">

											<button type="button" class="btn btn-light"
												onclick="clearSearch('housingSearch', 'housingColumnsList', 'housingCounter')">
												مسح
											</button>
										</div>

										<div class="text-muted fs-7 mt-2">
											عدد النتائج الكلية:
											<span id="housingCounter"><?php echo e(count($housingColumns)); ?></span>
											/ <?php echo e(count($housingColumns)); ?>

										</div>
									</div>

									<div class="row" id="housingColumnsList">
										<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $housingColumns; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $column): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoop($loop->index); ?><?php endif; ?>
											<div class="col-md-6 mb-3 column-item">
												<label
													class="form-check form-check-custom form-check-solid align-items-start w-100 border rounded p-3">
													<input class="form-check-input mt-1" type="checkbox"
														name="housing_columns[]" value="<?php echo e($column); ?>">

													<span class="form-check-label ms-3 w-100">
														<strong class="d-block fs-6 searchable-label">
															<?php echo e($assessmentMeta[$column]['label'] ?? ucwords(str_replace('_', ' ', $column))); ?>

														</strong>

														<small class="text-muted d-block mt-1 searchable-column">
															<?php echo e($assessmentMeta[$column]['hint'] ?? ''); ?>

														</small>

														<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!empty($assessmentMeta[$column]['hint']) && trim($assessmentMeta[$column]['hint']) !== ''): ?>
															<small class="text-info d-block mt-2 searchable-hint">
																<?php echo e($column); ?>

															</small>
														<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
													</span>
												</label>
											</div>
										<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
											<div class="col-12">
												<div class="alert alert-warning mb-0">
													لا توجد حقول متاحة في جدول housing_units
												</div>
											</div>
										<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
									</div>
								</div>
							</div>
						</div>
					</div>

					<div id="exportResult" class="mt-4"></div>

				</form>
			</div>
		</div>
	</div>


<?php $__env->stopSection(); ?>

<?php $__env->startSection('script'); ?>

	<script>
		let exportInterval = null;
		let isDownloaded = false;

		function resetFilters() {
			$('.filter-select2').val(null).trigger('change');
		}

		function toggleVisibleGroup(listId, inputName, checked) {
			const list = document.getElementById(listId);
			if (!list) return;

			const visibleItems = list.querySelectorAll('.column-item');

			visibleItems.forEach(function (item) {
				if (item.style.display !== 'none') {
					const checkbox = item.querySelector('input[name="' + inputName + '"]');
					if (checkbox) {
						checkbox.checked = checked;
					}
				}
			});
		}

		function filterColumns(inputId, listId, counterId) {
			const input = document.getElementById(inputId);
			const list = document.getElementById(listId);
			const counter = document.getElementById(counterId);

			if (!input || !list || !counter) return;

			const filter = input.value.toLowerCase().trim();
			const items = list.querySelectorAll('.column-item');

			let visibleCount = 0;

			items.forEach(function (item) {
				const text = item.innerText.toLowerCase();

				if (text.includes(filter)) {
					item.style.display = '';
					visibleCount++;
				} else {
					item.style.display = 'none';
				}
			});

			counter.innerText = visibleCount;
		}

		function clearSearch(inputId, listId, counterId) {
			const input = document.getElementById(inputId);
			if (!input) return;

			input.value = '';
			filterColumns(inputId, listId, counterId);
			input.focus();
		}

		function filterFilterCards() {
			const input = document.getElementById('filterSearch');
			const counter = document.getElementById('filterCardsCounter');
			if (!input || !counter) return;

			const filter = input.value.toLowerCase().trim();
			const items = document.querySelectorAll('#filtersCardsList .filter-card-item');

			let visibleCount = 0;

			items.forEach(function (item) {
				const text = item.innerText.toLowerCase();

				if (text.includes(filter)) {
					item.style.display = '';
					visibleCount++;
				} else {
					item.style.display = 'none';
				}
			});

			counter.innerText = visibleCount;
		}

		function clearFilterSearch() {
			const input = document.getElementById('filterSearch');
			if (!input) return;

			input.value = '';
			filterFilterCards();
			input.focus();
		}

		function showError(message) {
			$('#exportResult').html(`
				<div class="alert alert-danger text-center">
					${message}
				</div>
			`);

			$('.export-btn').prop('disabled', false);

			if (exportInterval) {
				clearInterval(exportInterval);
				exportInterval = null;
			}

			isDownloaded = false;
		}

		document.addEventListener('DOMContentLoaded', function () {
			filterColumns('buildingSearch', 'buildingColumnsList', 'buildingCounter');
			filterColumns('housingSearch', 'housingColumnsList', 'housingCounter');
			filterFilterCards();

			$('.filter-select2').select2({
				width: '100%',
				dir: 'rtl',
				closeOnSelect: false,
				placeholder: 'اختر القيم'
			});

			const collapse = document.getElementById('filtersCollapse');
			const btn = document.getElementById('toggleFiltersBtn');

			if (collapse && btn) {
				collapse.addEventListener('shown.bs.collapse', function () {
					btn.innerHTML = '<i class="fas fa-chevron-down me-1"></i> إخفاء';
				});

				collapse.addEventListener('hidden.bs.collapse', function () {
					btn.innerHTML = '<i class="fas fa-chevron-left me-1"></i> إظهار';
				});
			}

			$('.export-btn').on('click', function (e) {
				e.preventDefault();

				if ($('.export-btn').prop('disabled')) return;

				const exportType = $(this).data('type');
				const formData = $('#exportForm').serializeArray();
				formData.push({ name: 'export_type', value: exportType });

				$('.export-btn').prop('disabled', true);
				isDownloaded = false;

				if (exportInterval) {
					clearInterval(exportInterval);
					exportInterval = null;
				}

				$('#exportResult').html(`
					<div class="card p-4 text-center">
						<h5 class="mb-3">
							⏳ جاري تجهيز الملف...
							<span class="spinner-border spinner-border-sm ms-2"></span>
						</h5>

						<div class="progress mb-3" style="height: 25px;">
							<div id="progressBar"
								 class="progress-bar progress-bar-striped progress-bar-animated bg-primary"
								 style="width: 0%">
								0%
							</div>
						</div>

						<div id="processedCount" class="text-muted small mt-2"></div>
					</div>
				`);

				$.ajax({
					url: "<?php echo e(route('export.start')); ?>",
					method: "POST",
					data: formData,
					success: function (res) {
						if (!res.status) {
							showError("❌ فشل بدء التصدير");
							return;
						}

						const exportId = res.export_id;

						exportInterval = setInterval(function () {
							$.get('<?php echo e(url('export/status')); ?>/' + exportId, function (data) {
								let progress = data.progress ?? 0;
								let processed = data.processed ?? 0;

								$('#progressBar')
									.css('width', progress + '%')
									.text(progress + '%');

								$('#processedCount').html(`
									تمت معالجة <b>${processed.toLocaleString()}</b> صف
								`);

								if (progress < 30) {
									$('#progressBar').attr('class', 'progress-bar bg-danger progress-bar-striped progress-bar-animated');
								} else if (progress < 70) {
									$('#progressBar').attr('class', 'progress-bar bg-warning progress-bar-striped progress-bar-animated');
								} else {
									$('#progressBar').attr('class', 'progress-bar bg-success progress-bar-striped progress-bar-animated');
								}

								if (data.status === 'done' && !isDownloaded) {
									isDownloaded = true;

									if (exportInterval) {
										clearInterval(exportInterval);
										exportInterval = null;
									}

									$('#progressBar')
										.removeClass('progress-bar-animated')
										.addClass('bg-success')
										.css('width', '100%')
										.text('100%');

									$('#exportResult').append(`
										<div class="alert alert-success mt-3">
											✅ تم إنشاء الملف بنجاح
										</div>
									`);

									setTimeout(() => {
										const link = document.createElement('a');
										link.href = data.file;
										link.download = '';
										document.body.appendChild(link);
										link.click();
										document.body.removeChild(link);

										$('.export-btn').prop('disabled', false);
									}, 800);
								}

								if (data.status === 'failed') {
									showError("❌ فشل التصدير");
								}
							}).fail(function () {
								showError("❌ تعذر التحقق من حالة التصدير");
							});
						}, 1000);
					},
					error: function () {
						showError("❌ خطأ في الاتصال بالسيرفر");
					}
				});
			});
		});
	</script>
<?php $__env->stopSection(); ?>
<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH D:\myProjects\phc\resources\views/exports/index.blade.php ENDPATH**/ ?>