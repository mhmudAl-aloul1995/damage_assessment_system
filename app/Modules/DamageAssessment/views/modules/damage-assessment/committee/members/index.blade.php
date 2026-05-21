@extends('layouts.app')

@section('title', 'أعضاء اللجنة')
@section('pageName', 'أعضاء اللجنة')

@section('content')
    @if (session('success'))
        <div class="alert alert-success mb-5">{{ session('success') }}</div>
    @endif

    <div class="card card-flush shadow-sm mb-5">
        <div class="card-header pt-6 d-flex justify-content-between align-items-center">
            <div class="card-title">
                <h3 class="fw-bold m-0">Committee Members</h3>
            </div>
            <button type="button" class="btn btn-primary" id="open_committee_member_modal">إضافة عضو</button>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table id="committee_members_table" class="table table-row-bordered table-row-gray-100 align-middle gs-0 gy-3 w-100">
                    <thead>
                        <tr class="fw-bold text-muted bg-light">
                            <th>الاسم</th>
                            <th>الجوال</th>
                            <th>الصفة</th>
                            <th>المستخدم المرتبط</th>
                            <th>الترتيب</th>
                            <th>الحالة</th>
                            <th>التوقيع</th>
                            <th class="text-end">الإجراء</th>
                        </tr>
                    </thead>
                </table>
            </div>
        </div>
    </div>

    <div class="modal fade" id="committee_member_modal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered mw-650px">
            <div class="modal-content">
                <div class="modal-header">
                    <h3 class="fw-bold m-0" id="committee_member_modal_title">إضافة عضو لجنة</h3>
                    <button type="button" class="btn btn-icon btn-sm btn-active-light-primary" data-bs-dismiss="modal">
                        <i class="ki-duotone ki-cross fs-1"></i>
                    </button>
                </div>
                <form id="committee_member_form" method="POST" action="{{ route('committee-members.store') }}">
                    @csrf
                    <input type="hidden" name="_method" id="committee_member_form_method" value="POST">
                    <div class="modal-body py-10 px-lg-17">
                        <div class="row g-5">
                            <div class="col-md-6">
                                <label class="form-label required">الاسم</label>
                                <input type="text" name="name" class="form-control form-control-solid" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">الجوال</label>
                                <input type="text" name="phone" class="form-control form-control-solid">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">الصفة</label>
                                <input type="text" name="title" class="form-control form-control-solid">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">المستخدم المرتبط</label>
                                <select name="user_id" class="form-select form-select-solid">
                                    <option value="">بدون ربط</option>
                                    @foreach ($users as $user)
                                        <option value="{{ $user->id }}">{{ $user->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">ترتيب التوقيع</label>
                                <input type="number" name="sort_order" class="form-control form-control-solid" value="0" min="0">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">مسار التوقيع</label>
                                <input type="text" name="signature_path" class="form-control form-control-solid">
                            </div>
                            <div class="col-md-6">
                                <label class="form-check form-switch form-check-custom form-check-solid mt-8">
                                    <input class="form-check-input" type="checkbox" name="is_active" value="1" checked>
                                    <span class="form-check-label fw-semibold text-muted">عضو مفعل</span>
                                </label>
                            </div>
                            <div class="col-md-6">
                                <label class="form-check form-switch form-check-custom form-check-solid mt-8">
                                    <input class="form-check-input" type="checkbox" name="is_required" value="1" checked>
                                    <span class="form-check-label fw-semibold text-muted">توقيعه مطلوب</span>
                                </label>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">إلغاء</button>
                        <button type="submit" class="btn btn-primary">حفظ</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection

@section('script')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const modalElement = document.getElementById('committee_member_modal');
            const modal = new bootstrap.Modal(modalElement);
            const form = document.getElementById('committee_member_form');
            const methodInput = document.getElementById('committee_member_form_method');

            $('#committee_members_table').DataTable({
                processing: true,
                serverSide: true,
                ajax: '{{ route('committee-members.data') }}',
                order: [[4, 'asc']],
                columns: [
                    { data: 'name', name: 'name' },
                    { data: 'phone', name: 'phone' },
                    { data: 'title', name: 'title' },
                    { data: 'user_id', name: 'user.name', orderable: false },
                    { data: 'sort_order', name: 'sort_order' },
                    { data: 'is_active', name: 'is_active' },
                    { data: 'is_required', name: 'is_required' },
                    { data: 'actions', name: 'actions', orderable: false, searchable: false, className: 'text-end' },
                ]
            });

            $('#open_committee_member_modal').on('click', function () {
                form.reset();
                form.action = '{{ route('committee-members.store') }}';
                methodInput.value = 'POST';
                $('#committee_member_modal_title').text('إضافة عضو لجنة');
                modal.show();
            });

            $(document).on('click', '.committee-member-edit', function () {
                const member = $(this).data('member');
                form.action = '{{ url('committee-members') }}/' + member.id;
                methodInput.value = 'PUT';
                $('#committee_member_modal_title').text('تعديل عضو لجنة');
                form.querySelector('[name="name"]').value = member.name ?? '';
                form.querySelector('[name="phone"]').value = member.phone ?? '';
                form.querySelector('[name="title"]').value = member.title ?? '';
                form.querySelector('[name="user_id"]').value = member.user_id ?? '';
                form.querySelector('[name="sort_order"]').value = member.sort_order ?? 0;
                form.querySelector('[name="signature_path"]').value = member.signature_path ?? '';
                form.querySelector('[name="is_active"]').checked = Boolean(member.is_active);
                form.querySelector('[name="is_required"]').checked = Boolean(member.is_required);
                modal.show();
            });
        });
    </script>
@endsection
