<label class="col-md-3">الرمز<input name="code" required maxlength="50" class="form-control" value="{{ $type?->code }}"></label>
<label class="col-md-3">الاسم<input name="name" required maxlength="255" class="form-control" value="{{ $type?->name }}"></label>
<label class="col-md-3">الاسم الإنجليزي<input name="name_en" maxlength="255" class="form-control" value="{{ $type?->name_en }}"></label>
<label class="col-md-3">الحالة<select name="active" class="form-select"><option value="1" @selected($type?->active!==false)>فعال</option><option value="0" @selected($type?->active===false)>مؤرشف</option></select></label>
<label class="col-md-6">مدة العقد<select name="requires_end" class="form-select"><option value="0" @selected(!$type?->requires_end)>يسمح بنهاية غير محددة</option><option value="1" @selected($type?->requires_end)>يجب تحديد نهاية</option></select></label>
<label class="col-md-6">التنبيه قبل الانتهاء بالأيام (مثال: 30,7)<input name="reminder_days" class="form-control" value="{{ implode(',',$type?->reminder_days??[]) }}"></label>
