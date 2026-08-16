"use strict";

var KTHousingExportModals = function () {
    var errorText = "Please fix the highlighted errors and try again.";
    var cancelText = "Are you sure you want to cancel?";

    var buildExportUrl = function (format) {
        return housing_export_url.replace('__FORMAT__', encodeURIComponent(format));
    };

    var serializeForms = function (form, includeFilters) {
        var payload = $(form).serialize();

        if (includeFilters) {
            payload += '&' + $("#filter_housing_form").serialize();
        }

        return payload;
    };

    var showExportLoading = function (button) {
        button.setAttribute('data-kt-indicator', 'on');
        button.disabled = true;

        if (typeof showAppLoading === 'function') {
            showAppLoading();
        }
    };

    var hideExportLoading = function (button) {
        button.removeAttribute('data-kt-indicator');
        button.disabled = false;

        if (typeof hideAppLoading === 'function') {
            hideAppLoading();
        }
    };

    var startDownload = function (url, button) {
        var iframe = document.createElement('iframe');
        var timeout = window.setTimeout(function () {
            hideExportLoading(button);
            iframe.remove();
        }, 120000);

        iframe.style.display = 'none';
        iframe.onload = function () {
            window.clearTimeout(timeout);
            hideExportLoading(button);
            window.setTimeout(function () {
                iframe.remove();
            }, 1000);
        };

        iframe.src = url;
        document.body.appendChild(iframe);
    };

    var initExportModal = function (config) {
        var element = document.getElementById(config.modalId);

        if (!element) {
            return;
        }

        var form = element.querySelector(config.formSelector);
        var modal = new bootstrap.Modal(element);
        var fields = {
            'format': {
                validators: {
                    notEmpty: {
                        message: 'File format is required'
                    }
                }
            }
        };

        if (config.requireObjectIds) {
            fields.objectids = {
                validators: {
                    notEmpty: {
                        message: 'Housing Unit Object IDs are required'
                    }
                }
            };
        }

        var validator = FormValidation.formValidation(form, {
            fields: fields,
            plugins: {
                trigger: new FormValidation.plugins.Trigger(),
                bootstrap: new FormValidation.plugins.Bootstrap5({
                    rowSelector: '.fv-row',
                    eleInvalidClass: '',
                    eleValidClass: ''
                })
            }
        });

        var submitButton = element.querySelector('[data-kt-housing-modal-action="submit"]');
        submitButton.addEventListener('click', function (e) {
            e.preventDefault();

            validator.validate().then(function (status) {
                if (status !== 'Valid') {
                    Swal.fire({
                        text: errorText,
                        icon: "error",
                        buttonsStyling: false,
                        confirmButtonText: "OK",
                        customClass: {
                            confirmButton: "btn btn-primary"
                        }
                    });

                    return;
                }

                showExportLoading(submitButton);

                var format = ($(form).find('[name="format"]').val() || 'xlsx').toString().toLowerCase();
                var exportUrl = buildExportUrl(format);

                startDownload(exportUrl + "?" + serializeForms(form, config.includeFilters), submitButton);
            });
        });

        element.querySelectorAll('[data-kt-housing-modal-action="close"]').forEach(function (cancelButton) {
            cancelButton.addEventListener('click', function (e) {
                e.preventDefault();

                Swal.fire({
                    text: cancelText,
                    icon: "warning",
                    showCancelButton: true,
                    buttonsStyling: false,
                    confirmButtonText: "Yes",
                    cancelButtonText: "No",
                    customClass: {
                        confirmButton: "btn btn-primary",
                        cancelButton: "btn btn-active-light"
                    }
                }).then(function (result) {
                    if (!result.value) {
                        return;
                    }

                    element.querySelectorAll('select').forEach(function (select) {
                        $(select).val('').trigger('change');
                    });

                    form.reset();
                    modal.hide();
                });
            });
        });
    };

    return {
        init: function () {
            initExportModal({
                modalId: 'kt_modal_export_housing',
                formSelector: '#kt_modal_export_housing_form',
                includeFilters: true,
                requireObjectIds: false
            });

            initExportModal({
                modalId: 'kt_modal_export_housing_boq_objectids',
                formSelector: '#kt_modal_export_housing_boq_objectids_form',
                includeFilters: false,
                requireObjectIds: true
            });
        }
    };
}();

KTUtil.onDOMContentLoaded(function () {
    KTHousingExportModals.init();
});
