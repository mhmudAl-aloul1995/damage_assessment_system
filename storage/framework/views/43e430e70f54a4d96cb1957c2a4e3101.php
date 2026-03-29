 

<?php $__env->startSection('title', 'الإستبيانات'); ?> 

<?php $__env->startSection('content'); ?> 
<style> 
    /* تحسينات عامة */
    .card { border: none; box-shadow: 0 0.1rem 1rem 0.25rem rgba(0, 0, 0, 0.05); transition: transform 0.2s ease; }
    .stat-card:hover { transform: translateY(-3px); }
    
    /* أيقونات البحث */
    .search-container { position: relative; }
    .search-container i { position: absolute; left: 15px; top: 50%; transform: translateY(-50%); color: #a1a5b7; }
    .search-container input { padding-left: 35px !important; border-radius: 8px; }

    /* تحسين السكيلتون */
    .skeleton { 
        background: linear-gradient(90deg, #f2f2f2 25%, #e6e6e6 37%, #f2f2f2 63%); 
        background-size: 400% 100%; 
        animation: skeleton-loading 1.4s ease infinite; 
    } 
    @keyframes skeleton-loading { 0% { background-position: 100% 50%; } 100% { background-position: 0 50%; } }
</style> 

<!-- Header & Title -->
<div class="d-flex flex-wrap flex-stack mb-6">  
    <h3 class="fw-bolder my-2">
        <i class="ki-duotone ki-abstract-26 fs-1 text-primary me-2"><span class="path1"></span><span class="path2"></span></i>
        قائمة الإستبيانات
    </h3>  
</div> 

<!-- Profile Header Card -->
<div class="card mb-8"> 
    <div class="card-body pt-9 pb-0"> 
        <div class="d-flex flex-wrap flex-sm-nowrap mb-6"> 
            <!-- Avatar -->
            <div class="me-7 mb-4"> 
                <div class="symbol symbol-100px symbol-lg-120px symbol-fixed position-relative"> 
                    <img src="assets/media/avatars/300-1.jpg" class="rounded-3" alt="user" /> 
                    <div class="position-absolute translate-middle bottom-0 start-100 mb-6 bg-success rounded-circle border border-4 border-body h-20px w-20px"></div> 
                </div> 
            </div> 

            <div class="flex-grow-1"> 
                <div class="d-flex justify-content-between align-items-start flex-wrap mb-2"> 
                    <div class="d-flex flex-column"> 
                        <div class="d-flex align-items-center mb-1"> 
                            <a href="#" class="text-gray-900 text-hover-primary fs-2 fw-bolder me-1">Max Smith</a> 
                            <span class="badge badge-light-primary fw-bold px-4 py-3">مهندس ميداني</span>
                        </div> 
                        <div class="d-flex flex-wrap fw-semibold fs-6 mb-4 pe-2"> 
                            <span class="d-flex align-items-center text-gray-500 me-5 mb-2">
                                <i class="ki-duotone ki-geolocation fs-4 me-1 text-primary"><span class="path1"></span><span class="path2"></span></i><?php echo e($assignedto); ?>

                            </span> 
                            <span class="d-flex align-items-center text-gray-500 mb-2"> 
                                <i class="ki-duotone ki-sms fs-4 me-1 text-primary"><span class="path1"></span><span class="path2"></span></i><?php echo e($assignedto); ?>@gmail.com
                            </span> 
                        </div> 
                    </div> 
                </div> 
                
                <!-- Stats Row -->
                <div class="row g-4 mb-6"> 
                    <div class="col-6 col-md-3">
                        <div class="bg-light-success rounded border border-success border-dashed p-4 text-center stat-card">
                            <div class="fs-2 fw-bolder text-success"><?php echo e($completed); ?></div>
                            <div class="fw-bold text-gray-600">مكتملة</div>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="bg-light-danger rounded border border-danger border-dashed p-4 text-center stat-card">
                            <div class="fs-2 fw-bolder text-danger"><?php echo e($notCompleted); ?></div>
                            <div class="fw-bold text-gray-600">غير مكتملة</div>
                        </div>
                    </div>
                    <div class="col-12 col-md-6">
                        <div class="bg-light-primary rounded border border-primary border-dashed p-4 stat-card">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="fw-bolder text-gray-800">نسبة الإنجاز الكلية</span>
                                <span class="badge badge-primary fs-7"><?php echo e($completion); ?>%</span>
                            </div>
                            <div class="h-8px w-100 bg-white rounded">
                                <div class="bg-primary rounded h-8px" role="progressbar" style="width: <?php echo e($completion); ?>%;"></div>
                            </div>
                        </div>
                    </div>
                </div> 
            </div> 
        </div>  
    </div> 
</div> 

<!-- Filters Bar -->
<div class="row g-4 mb-8 align-items-center">  
    <div class="col-md-4 col-lg-3">  
        <div class="search-container">
            <i class="ki-duotone ki-magnifier fs-2"></i>
            <input type="text" id="search" class="form-control form-control-solid border-0" placeholder="بحث عن مالك أو مبنى...">  
        </div>
    </div> 
    <div class="col-md-4 col-lg-3">  
        <select name="field_status" class="form-select form-select-solid border-0 fw-bold text-gray-600"> 
            <option value="all">جميع الحالات</option> 
            <option value="COMPLETED">المكتملة فقط</option> 
            <option value="NOT_COMPLETED">غير المكتملة</option> 
        </select>  
    </div>  
</div> 

<!-- Dynamic Content Area -->
<div id="engineers-container" class="min-h-300px"></div> 

<!-- Enhanced Skeleton Loader -->
<div id="skeleton-loader" class="d-none">  
    <div class="row g-6">  
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php for($i = 0; $i < 3; $i++): ?>  
            <div class="col-md-6 col-xl-4">  
                <div class="card h-100 p-6">
                    <div class="skeleton rounded-circle mb-4" style="width: 50px; height: 50px;"></div>
                    <div class="skeleton rounded mb-3" style="width: 70%; height: 20px;"></div>
                    <div class="skeleton rounded mb-2" style="width: 100%; height: 15px;"></div>
                    <div class="skeleton rounded" style="width: 40%; height: 15px;"></div>
                </div>  
            </div>  
        <?php endfor; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>  
    </div>  
</div> 
<?php $__env->stopSection(); ?> 

<?php $__env->startSection('script'); ?> 
<script> 
    let searchTimeout;

    function loadEngineers(url) { 
        let status = $('select[name="field_status"]').val(); 
        let search = $("#search").val(); 
        let assignedto = "<?php echo e($assignedto); ?>"; 

        $("#engineers-container").html($("#skeleton-loader").html()); 

        $.ajax({ 
            url: url, 
            type: "GET", 
            data: { status: status, assignedto: assignedto, search: search }, 
            success: function (data) { 
                $("#engineers-container").hide().html(data).fadeIn(300); 
            }
        }); 
    } 

    $(document).ready(function () { 
        $('select[name="field_status"]').on('change', () => loadEngineers("<?php echo e(route('engineers.filter')); ?>")); 
        
        loadEngineers("<?php echo e(route('engineers.filter')); ?>"); 

        $("#search").on("input", function () { 
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => loadEngineers("<?php echo e(route('engineers.filter')); ?>"), 400);
        }); 
    }); 

    $(document).on('click', '.pagination a', function (e) { 
        e.preventDefault(); 
        loadEngineers($(this).attr('href')); 
    }); 
</script> 
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH D:\myProjects\phc\resources\views/DamageAssessment/engineerAssessments.blade.php ENDPATH**/ ?>