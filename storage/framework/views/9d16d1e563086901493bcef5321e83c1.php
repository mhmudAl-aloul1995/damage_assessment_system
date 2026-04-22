<?php
    $isRtl = app()->getLocale() === 'ar';
    $direction = $isRtl ? 'rtl' : 'ltr';
    $suffix = $isRtl ? '.rtl' : '';
?>
<!DOCTYPE html>
<html lang="<?php echo e(app()->getLocale()); ?>" dir="<?php echo e($direction); ?>" style="direction: <?php echo e($direction); ?>">
<head>
    <base href="../../../" />
    <title><?php echo e(__('ui.app.damage_program')); ?> - <?php echo e(__('ui.app.name')); ?></title>
    <meta charset="utf-8" />
    <meta name="description" content="<?php echo e(__('ui.app.damage_program')); ?> - <?php echo e(__('ui.app.name')); ?>" />
    <meta name="keywords" content="<?php echo e(__('ui.app.damage_program')); ?> - <?php echo e(__('ui.app.name')); ?>" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <meta property="og:locale" content="<?php echo e($isRtl ? 'ar_PS' : 'en_US'); ?>" />
    <meta property="og:type" content="article" />
    <meta property="og:title" content="<?php echo e(__('ui.app.damage_program')); ?>" />
    <meta property="og:url" content="<?php echo e(url()->current()); ?>" />
    <meta property="og:site_name" content="<?php echo e(__('ui.app.name')); ?>" />
    <link rel="shortcut icon" href="<?php echo e(asset('assets/media/logos/favicon.ico')); ?>" />
    <link rel="stylesheet" href="<?php echo e(asset('assets/css/fontface.css')); ?>">
    <link href="<?php echo e(asset('assets/plugins/global/plugins.bundle' . $suffix . '.css')); ?>" rel="stylesheet" type="text/css" />
    <link href="<?php echo e(asset('assets/css/style.bundle' . $suffix . '.css')); ?>" rel="stylesheet" type="text/css" />
    <link href="<?php echo e(asset('assets/css/font-unified.css')); ?>" rel="stylesheet" type="text/css" />
    <script>if (window.top != window.self) { window.top.location.replace(window.self.location.href); }</script>
</head>
<body id="kt_body" class="app-blank bgi-size-cover bgi-attachment-fixed bgi-position-center bgi-no-repeat">
    <script>var defaultThemeMode = "light"; var themeMode; if (document.documentElement) { if (document.documentElement.hasAttribute("data-bs-theme-mode")) { themeMode = document.documentElement.getAttribute("data-bs-theme-mode"); } else { if (localStorage.getItem("data-bs-theme") !== null) { themeMode = localStorage.getItem("data-bs-theme"); } else { themeMode = defaultThemeMode; } } if (themeMode === "system") { themeMode = window.matchMedia("(prefers-color-scheme: dark)").matches ? "dark" : "light"; } document.documentElement.setAttribute("data-bs-theme", themeMode); }</script>

    <div class="d-flex flex-column flex-root" id="kt_app_root">
        <style>
            body {
                background-image: url('<?php echo e(asset('assets/media/auth/bg4.jpg')); ?>');
            }

            [data-bs-theme="dark"] body {
                background-image: url('<?php echo e(asset('assets/media/auth/bg4-dark.jpg')); ?>');
            }
        </style>

        <div class="d-flex flex-column flex-column-fluid flex-lg-row">
            <div class="d-flex flex-center w-lg-50 pt-15 pt-lg-0 px-10">
                <div class="d-flex flex-center flex-lg-start flex-column">
                    <div class="mb-7 d-flex align-items-center gap-3">
                        <a href="<?php echo e(route('login')); ?>">
                            <img style="max-width: 400px; height: auto;" class="h-100px" alt="Logo" src="<?php echo e(asset('assets/media/logos/LogoGaza2.jpeg')); ?>" />
                        </a>

                        <div class="btn-group" role="group" aria-label="<?php echo e(__('ui.locale.switcher')); ?>">
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = config('app.supported_locales', ['en']); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $locale): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoop($loop->index); ?><?php endif; ?>
                                <form method="POST" action="<?php echo e(route('locale.update', $locale)); ?>" class="locale-switcher-form" data-locale="<?php echo e($locale); ?>">
                                    <?php echo csrf_field(); ?>
                                    <button type="submit" class="btn btn-sm <?php echo e(app()->getLocale() === $locale ? 'btn-primary' : 'btn-light'); ?>">
                                        <?php echo e(__('ui.locale.' . ($locale === 'ar' ? 'arabic' : 'english'))); ?>

                                    </button>
                                </form>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                        </div>
                    </div>

                    <h1 class="text-white fw-normal m-0"><?php echo e(__('ui.app.name')); ?></h1>
                </div>
            </div>

            <div class="d-flex flex-column-fluid flex-lg-row-auto justify-content-center justify-content-lg-end p-12 p-lg-20">
                <div class="bg-body d-flex flex-column align-items-stretch flex-center rounded-4 w-md-600px p-20">
                    <div class="d-flex flex-center flex-column flex-column-fluid px-lg-10 pb-15 pb-lg-20">
                        <form class="form w-100" data-kt-redirect-url="<?php echo e(url('/')); ?>" novalidate="novalidate" id="kt_sign_in_form" method="POST" action="<?php echo e(route('login')); ?>">
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="remember" value="true" />

                            <div class="text-center mb-11">
                                <h1 class="text-dark fw-bolder mb-3"><?php echo e(__('ui.auth.sign_in')); ?></h1>
                            </div>

                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(session('error')): ?>
                                <div class="alert alert-danger"><?php echo e(session('error')); ?></div>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($errors->any()): ?>
                                <div class="alert alert-danger">
                                    <ul class="mb-0">
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $errors->all(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $error): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoop($loop->index); ?><?php endif; ?>
                                            <li><?php echo e($error); ?></li>
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                                    </ul>
                                </div>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                            <div class="fv-row mb-8">
                                <input type="email" placeholder="<?php echo e(__('ui.auth.email')); ?>" name="email" value="<?php echo e(old('email')); ?>"
                                    autocomplete="off" class="form-control bg-transparent <?php $__errorArgs = ['email'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>" required autofocus />
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['email'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
                                    <div class="invalid-feedback"><?php echo e($message); ?></div>
                                <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            </div>

                            <div class="fv-row mb-3">
                                <input type="password" placeholder="<?php echo e(__('ui.auth.password')); ?>" name="password" autocomplete="off"
                                    class="form-control bg-transparent <?php $__errorArgs = ['password'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>" required />
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['password'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
                                    <div class="invalid-feedback"><?php echo e($message); ?></div>
                                <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            </div>

                            <div class="d-flex flex-stack flex-wrap gap-3 fs-base fw-semibold mb-8">
                                <div>
                                    <label class="form-check form-check-inline">
                                        <input class="form-check-input" type="checkbox" name="remember" />
                                        <span class="form-check-label text-gray-700 fs-base ms-1"><?php echo e(__('ui.auth.remember_me')); ?></span>
                                    </label>
                                </div>
                                <a href="<?php echo e(route('password.request')); ?>" class="link-primary"><?php echo e(__('ui.auth.forgot_password')); ?></a>
                            </div>

                            <div class="d-grid mb-10">
                                <button type="submit" id="kt_sign_in_submit" class="btn btn-primary">
                                    <span class="indicator-label"><?php echo e(__('ui.auth.sign_in')); ?></span>
                                    <span class="indicator-progress"><?php echo e(__('ui.auth.please_wait')); ?>

                                        <span class="spinner-border spinner-border-sm align-middle ms-2"></span>
                                    </span>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>var hostUrl = "assets/";</script>
    <script src="<?php echo e(asset('assets/plugins/global/plugins.bundle.js')); ?>"></script>
    <script src="<?php echo e(asset('assets/js/scripts.bundle.js')); ?>"></script>
    <script src="<?php echo e(asset('assets/js/custom/authentication/sign-in/general.js')); ?>"></script>
    <script>
        const persistedLocale = document.documentElement.lang;

        if (persistedLocale) {
            localStorage.setItem('preferred_locale', persistedLocale);
        }

        document.querySelectorAll('.locale-switcher-form').forEach((form) => {
            form.addEventListener('submit', function () {
                const locale = this.dataset.locale;

                if (locale) {
                    localStorage.setItem('preferred_locale', locale);
                }
            });
        });
    </script>
</body>
</html>
<?php /**PATH D:\myProjects\phc\resources\views/auth/login.blade.php ENDPATH**/ ?>