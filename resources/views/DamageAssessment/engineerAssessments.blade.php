@extends('layouts.app')
@section('title', '')
@section('pageName', '')

@section('content')
	<style>
		.small,
		.text-muted {
			display: none;

		}
	</style>
	<div class="d-flex flex-wrap flex-stack mb-6">

		<h3 class="fw-bold my-2">الإستبيانات</h3>

	</div>
	<div class="d-flex flex-wrap flex-stack mb-6">

		<div class="me-4">

			<input type="text" id="search" class="form-control form-control-sm form-control-white  w-200px"
				placeholder="بحث عن اسم المالك أو المبنى">

		</div>
		<div class="d-flex flex-wrap my-4">

			<div class="me-6">

				<select name="field_status" class="form-select form-select-sm form-select-white w-200px">
					<option value="">حالة الإستبيان</option>
					<option value="all">الكل</option>
					<option value="COMPLETED">المكتملة</option>
					<option value="NOT_COMPLETED">الغير مكتملة</option>
				</select>

			</div>

		</div>

	</div>


	<div id="engineers-container">



	</div>


	<style>
		.skeleton {
			background: linear-gradient(90deg,
					#eeeeee 25%,
					#dddddd 37%,
					#eeeeee 63%);
			background-size: 400% 100%;
			animation: skeleton-loading 1.4s ease infinite;
			border-radius: 6px;
		}

		@keyframes skeleton-loading {
			0% {
				background-position: 100% 50%;
			}

			100% {
				background-position: 0 50%;
			}
		}

		.skeleton-avatar {
			width: 50px;
			height: 50px;
		}

		.skeleton-title {
			width: 80%;
			height: 20px;
		}

		.skeleton-text {
			width: 100%;
			height: 14px;
		}
	</style>


	<div id="skeleton-loader" class="d-none">

		<div class="row g-6 g-xl-9">

			@for ($i = 0; $i < 6; $i++)

				<div class="col-md-6 col-xl-4">

					<div class="card h-100 shadow-sm p-6">

						<div class="skeleton skeleton-avatar mb-4"></div>

						<div class="skeleton skeleton-title mb-3"></div>

						<div class="skeleton skeleton-text mb-2"></div>

						<div class="skeleton skeleton-text"></div>

					</div>

				</div>

			@endfor

		</div>

	</div>

@endsection


@section('script')

	<script>

		let request;

		function loadEngineers(url) {

			let status = $('select[name="field_status"]').val();
			let search = $("#search").val();
			let assignedto = "{{ $assignedto }}";

			$("#engineers-container").html($("#skeleton-loader").html());

			if (request) {
				request.abort();
			}

			request = $.ajax({

				url: url,
				type: "GET",

				data: {
					status: status,
					assignedto: assignedto,
					search: search
				},

				success: function (data) {

					$("#engineers-container").html(data);

				}

			});

		}

		$(document).ready(function () {

			$('select[name="field_status"]').on('change', function () {

				loadEngineers("{{ route('engineers.filter') }}");

			});
			$('select[name="field_status"]').val('all').trigger('change');

		});

		$(document).on('click', '.pagination a', function (e) {

			e.preventDefault();

			let url = $(this).attr('href');

			loadEngineers(url);

		});

		$("#search").on("keydown", function (e) {

			if (e.which == 13) { // Enter key

				e.preventDefault();

				loadEngineers("{{ route('engineers.filter') }}");

			}

		});
	</script>

@endsection