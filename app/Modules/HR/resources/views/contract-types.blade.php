@extends('layouts.app')
@section('title', 'أنواع العقود والتنبيهات')
@section('pageName', 'أنواع العقود والتنبيهات')
@section('content')
<div dir="rtl" lang="ar">@include('hr::workflow-nav')
<h1>أنواع العقود والتنبيهات</h1><p>مدد التنبيه قابلة للضبط لكل نوع؛ تركها فارغة يوقف تنبيهات الانتهاء لذلك النوع.</p>
@foreach($types as $type)<div class="card mb-4"><form class="card-body row g-3" method="POST" action="{{ route('hr.contracts.types.update',$type) }}">@csrf @method('PUT')
@include('hr::contract-type-fields',['type'=>$type])<div><button class="btn btn-primary">حفظ</button></div>
</form></div>@endforeach {{ $types->links() }}
<div class="card mt-5"><div class="card-body"><h2>نوع جديد</h2><form class="row g-3" method="POST" action="{{ route('hr.contracts.types.store') }}">@csrf @include('hr::contract-type-fields',['type'=>null])<div><button class="btn btn-primary">إضافة</button></div></form></div></div>
</div>
@endsection
