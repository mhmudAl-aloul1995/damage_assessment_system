"use strict";

var KTSigninGeneral = function () {
    var form;
    var submitButton;
    var validator;

    var handleValidation = function () {
        validator = FormValidation.formValidation(
            form,
            {
                fields: {
                    'email': {
                        validators: {
                            regexp: {
                                regexp: /^[^\s@]+@[^\s@]+\.[^\s@]+$/,
                                message: 'عنوان بريد إلكتروني غير صالح',
                            },
                            notEmpty: {
                                message: 'عنوان البريد الإلكتروني مطلوب'
                            }
                        }
                    },
                    'password': {
                        validators: {
                            notEmpty: {
                                message: 'كلمة المرور مطلوبة'
                            }
                        }
                    }
                },
                plugins: {
                    trigger: new FormValidation.plugins.Trigger(),
                    bootstrap: new FormValidation.plugins.Bootstrap5({
                        rowSelector: '.fv-row',
                        eleInvalidClass: '',
                        eleValidClass: ''
                    })
                }
            }
        );
    }

    var handleSubmitDemo = function () {
        submitButton.addEventListener('click', function (e) {
            e.preventDefault();

            validator.validate().then(function (status) {
                if (status == 'Valid') {
                    submitButton.setAttribute('data-kt-indicator', 'on');
                    submitButton.disabled = true;

                    form.submit();
                } else {
                    toastr.error("عذراً، يبدو أنه تم اكتشاف بعض الأخطاء، يرجى المحاولة مرة أخرى.");
                }
            });
        });
    }

    return {
        init: function () {
            form = document.querySelector('#kt_sign_in_form');
            submitButton = document.querySelector('#kt_sign_in_submit');

            handleValidation();
            handleSubmitDemo();
        }
    };
}();

KTUtil.onDOMContentLoaded(function () {
    KTSigninGeneral.init();
});