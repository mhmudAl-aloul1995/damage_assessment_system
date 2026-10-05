@extends('layouts.app')

@section('title', 'طلب إرجاع استبيان مبنى')
@section('pageName', 'طلب جديد')

@section('content')
    <div class="card card-flush">
        <div class="card-header pt-7">
            <div class="card-title">
                <h2>طلب إرجاع استبيان مبنى</h2>
            </div>
        </div>
        <div class="card-body">
            <div id="createReturnRequestErrors" class="alert alert-danger d-none"></div>

            <form id="createReturnRequestForm" action="{{ route('building-survey-return-requests.store') }}">
                @csrf
                <div class="mb-7">
                    <label class="required form-label fw-semibold">المبنى</label>
                    <select name="building_objectid" id="returnRequestBuildingSelect" class="form-select form-select-solid"
                        data-control="select2" data-placeholder="ابحث عن objectid أو اسم المبنى">
                        <option value=""></option>
                        @foreach ($buildings as $building)
                            <option value="{{ $building->objectid }}">
                                {{ $building->objectid }} - {{ $building->building_name ?? $building->globalid }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="mb-7">
                    <label class="form-label fw-semibold">سبب الطلب</label>
                    <textarea name="reason" class="form-control form-control-solid" rows="4"></textarea>
                </div>
                <div class="d-flex gap-3">
                    <button class="btn btn-primary" id="createReturnRequestSubmit" type="submit">
                        <span class="indicator-label">إرسال الطلب</span>
                        <span class="indicator-progress">يرجى الانتظار...
                            <span class="spinner-border spinner-border-sm align-middle ms-2"></span>
                        </span>
                    </button>
                    <a href="{{ route('building-survey-return-requests.index') }}" class="btn btn-light">رجوع</a>
                </div>
            </form>
        </div>
    </div>
@endsection

@section('script')
    <script>
        $('[data-control="select2"]').select2({
            dir: 'rtl',
            width: '100%'
        });

        $('#createReturnRequestForm').on('submit', function(event) {
            event.preventDefault();

            const form = $(this);
            const submitButton = $('#createReturnRequestSubmit');
            const errorsBox = $('#createReturnRequestErrors');

            submitButton.attr('data-kt-indicator', 'on').prop('disabled', true);
            errorsBox.addClass('d-none').html('');

            $.ajax({
                url: form.attr('action'),
                type: 'POST',
                data: form.serialize(),
                headers: {
                    Accept: 'application/json',
                },
                success: function(response) {
                    if (typeof toastr !== 'undefined') {
                        toastr.success(response.message || 'تم إرسال الطلب بنجاح.');
                    }

                    setTimeout(function() {
                        window.location.href = response.redirect_url ||
                            "{{ route('building-survey-return-requests.index') }}";
                    }, 500);
                },
                error: function(xhr) {
                    const errors = xhr.responseJSON?.errors ?
                        Object.values(xhr.responseJSON.errors).flat() : [xhr.responseJSON?.message ||
                            'تعذر إرسال الطلب.'
                        ];

                    errorsBox.removeClass('d-none').html(errors.join('<br>'));

                    if (typeof toastr !== 'undefined') {
                        toastr.error(errors[0] || 'تعذر إرسال الطلب.');
                    }
                },
                complete: function() {
                    submitButton.removeAttr('data-kt-indicator').prop('disabled', false);
                }
            });
        });
    </script>
@endsection
