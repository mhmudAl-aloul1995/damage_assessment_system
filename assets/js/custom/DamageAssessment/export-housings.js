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

        iframe.style.display = 'none';
        iframe.onload = function () {
            hideExportLoading(button);
            window.setTimeout(function () {
                iframe.remove();
            }, 1000);
        };

        iframe.src = url;
        document.body.appendChild(iframe);
    };

    var initColumnPicker = function (element) {
        var picker = element.querySelector('[data-housing-column-picker]');

        if (!picker) {
            return null;
        }

        var checkboxes = Array.prototype.slice.call(picker.querySelectorAll('[data-housing-column-checkbox]'));
        var searchInput = picker.querySelector('[data-housing-column-search]');
        var selectVisibleButton = picker.querySelector('[data-housing-columns-select-visible]');
        var clearButton = picker.querySelector('[data-housing-columns-clear]');
        var selectedCount = picker.querySelector('[data-housing-selected-count]');
        var selectedSummary = picker.querySelector('[data-housing-selected-summary]');
        var emptyState = picker.querySelector('[data-housing-columns-empty]');
        var selectedCountTemplate = picker.getAttribute('data-selected-count-template') || ':count selected';
        var moreTemplate = picker.getAttribute('data-more-template') || '+:count more';

        var visibleCheckboxes = function () {
            return checkboxes.filter(function (checkbox) {
                var item = checkbox.closest('.housing-export-column-item');

                return item && !item.classList.contains('d-none');
            });
        };

        var updateSelectedSummary = function () {
            var selected = checkboxes.filter(function (checkbox) {
                return checkbox.checked;
            });

            if (selectedCount) {
                selectedCount.textContent = selectedCountTemplate.replace(':count', selected.length);
            }

            if (!selectedSummary) {
                return;
            }

            selectedSummary.innerHTML = '';

            if (selected.length === 0) {
                var emptyText = document.createElement('span');
                emptyText.className = 'text-muted fs-7';
                emptyText.textContent = selectedSummary.getAttribute('data-empty-text') || '';
                selectedSummary.appendChild(emptyText);

                return;
            }

            selected.slice(0, 3).forEach(function (checkbox) {
                var chip = document.createElement('span');
                chip.className = 'badge badge-light-primary';
                chip.textContent = checkbox.getAttribute('data-column-label') || checkbox.value;
                selectedSummary.appendChild(chip);
            });

            if (selected.length > 3) {
                var moreChip = document.createElement('span');
                moreChip.className = 'badge badge-light';
                moreChip.textContent = moreTemplate.replace(':count', selected.length - 3);
                selectedSummary.appendChild(moreChip);
            }
        };

        var filterColumns = function () {
            var term = (searchInput ? searchInput.value : '').trim().toLowerCase();
            var visibleCount = 0;

            checkboxes.forEach(function (checkbox) {
                var item = checkbox.closest('.housing-export-column-item');
                var haystack = [
                    item ? item.getAttribute('data-column-label') : '',
                    item ? item.getAttribute('data-column-name') : ''
                ].join(' ');
                var isVisible = term === '' || haystack.indexOf(term) !== -1;

                if (item) {
                    item.classList.toggle('d-none', !isVisible);
                }

                if (isVisible) {
                    visibleCount++;
                }
            });

            if (emptyState) {
                emptyState.classList.toggle('d-none', visibleCount !== 0);
            }
        };

        checkboxes.forEach(function (checkbox) {
            checkbox.addEventListener('change', updateSelectedSummary);
        });

        if (searchInput) {
            searchInput.addEventListener('input', filterColumns);
        }

        if (selectVisibleButton) {
            selectVisibleButton.addEventListener('click', function () {
                visibleCheckboxes().forEach(function (checkbox) {
                    checkbox.checked = true;
                });

                updateSelectedSummary();
            });
        }

        if (clearButton) {
            clearButton.addEventListener('click', function () {
                checkboxes.forEach(function (checkbox) {
                    checkbox.checked = false;
                });

                updateSelectedSummary();
            });
        }

        updateSelectedSummary();

        return {
            reset: function () {
                checkboxes.forEach(function (checkbox) {
                    checkbox.checked = false;
                });

                if (searchInput) {
                    searchInput.value = '';
                }

                filterColumns();
                updateSelectedSummary();
            }
        };
    };

    var initExportModal = function (config) {
        var element = document.getElementById(config.modalId);

        if (!element) {
            return;
        }

        var form = element.querySelector(config.formSelector);
        var modal = new bootstrap.Modal(element);
        var columnPicker = initColumnPicker(element);
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
                    if (columnPicker) {
                        columnPicker.reset();
                    }
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
