"use strict";

// Class definition
var KTModalExportUsers = function () {
    // Shared variables
    const element = document.getElementById('kt_modal_export_buildings');
    const form = element.querySelector('#kt_modal_export_buildings_form');
    const modal = new bootstrap.Modal(element);

    // Init form inputs
    var initForm = function () {

        // Init form validation rules. For more info check the FormValidation plugin's official documentation:https://formvalidation.io/
        var validator = FormValidation.formValidation(
            form,
            {
                fields: {
                    'format': {
                        validators: {
                            notEmpty: {
                                message: 'File format is required'
                            }
                        }
                    },
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

        // Submit button handler
        const submitButton = element.querySelector('[data-kt-buildings-modal-action="submit"]');
        submitButton.addEventListener('click', function (e) {
            e.preventDefault();

            // Validate form before submit
            if (validator) {
                validator.validate().then(function (status) {
                    console.log('validated!');

                    if (status == 'Valid') {
                        submitButton.setAttribute('data-kt-indicator', 'on');
                        submitButton.disabled = true;

                        // Trigger the download
                        const params = $("#kt_modal_export_buildings_form").serialize() + '&' + $("#filter_buliding_form").serialize();
                        window.location.href = url_phc + "/export_building?" + params;

                        // Slight delay before showing success to let the browser initiate the stream
                        setTimeout(function () {
                            submitButton.removeAttribute('data-kt-indicator');
                            submitButton.disabled = false; // Re-enable so they can export again if needed

                            Swal.fire({
                                text: "جاري التحميل ..",
                                icon: "success",
                                buttonsStyling: false,
                                confirmButtonText: "حسناً",
                                customClass: { confirmButton: "btn btn-primary" }
                            }).then(function (result) {
                                if (result.isConfirmed) {
                                    modal.hide();
                                }
                            });
                        }, 4000);
                    }


                });
            }
        });

        // Cancel button handler
        const cancelButton = element.querySelector('[data-kt-buildings-modal-action="close"]');
        const selectOptions = element.querySelectorAll('select');

        cancelButton.addEventListener('click', function (e) {
            e.preventDefault();

            Swal.fire({
                text: "هل أنت متأكد من رغبتك في الإلغاء؟",
                icon: "warning",
                showCancelButton: true,
                buttonsStyling: false,
                confirmButtonText: "نعم، قم بإلغائه!",
                cancelButtonText: "لا، عودة",
                customClass: {
                    confirmButton: "btn btn-primary",
                    cancelButton: "btn btn-active-light"
                }
            }).then(function (result) {
                if (result.value) {

                    selectOptions.forEach(selectOptions => {
                        $(selectOptions).val('').trigger('change');
                    });

                    form.reset(); // Reset form	
                    modal.hide(); // Hide modal		

                } else if (result.dismiss === 'cancel') {
                    Swal.fire({
                        text: "لم يتم إلغاء طلبك!",
                        icon: "error",
                        buttonsStyling: false,
                        confirmButtonText: "حسناً، موافق!",
                        customClass: {
                            confirmButton: "btn btn-primary",
                        }
                    });
                }
            });
        });


    }

    return {
        // Public functions
        init: function () {
            initForm();
        }
    };
}();

// On document ready
KTUtil.onDOMContentLoaded(function () {
    KTModalExportUsers.init();
});
