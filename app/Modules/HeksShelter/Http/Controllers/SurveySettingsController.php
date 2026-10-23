<?php

namespace App\Modules\HeksShelter\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\HeksShelter\Http\Requests\SurveySettingsRequest;
use App\Modules\HeksShelter\Models\SurveyRevision;
use App\Modules\HeksShelter\Services\SurveyDesigner;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SurveySettingsController extends Controller
{
    public function index(Request $request, SurveyDesigner $designer): View
    {
        $draft = SurveyRevision::query()->where('status', 'draft')->latest('id')->first();
        $revision = $draft ?? $designer->published();
        $questions = $revision->questions()->with('choices')->get();
        $editing = $questions->firstWhere('id', (int) $request->query('question'));

        return view('heks-shelter::settings', ['revision' => $revision, 'questions' => $questions, 'editing' => $editing, 'revisions' => SurveyRevision::query()->with('author')->latest('id')->get(), 'sections' => $designer->sections($revision->id)]);
    }

    public function draft(Request $request, SurveyDesigner $designer): RedirectResponse
    {
        $designer->draft($request->user());

        return to_route('heks3.settings')->with('status', 'تم تجهيز مسودة قابلة للتعديل.');
    }

    public function save(SurveySettingsRequest $request, SurveyDesigner $designer): RedirectResponse
    {
        $designer->save($request->user(), $request->validated());

        return to_route('heks3.settings')->with('status', 'حُفظ السؤال في المسودة. انشر الإصدار لتفعيله في التطبيق.');
    }

    public function publish(SurveySettingsRequest $request, SurveyDesigner $designer): RedirectResponse
    {
        $designer->publish($request->user(), $request->integer('revision_id'), $request->integer('edit_version'));

        return to_route('heks3.settings')->with('status', 'تم نشر الإصدار وتجهيز أعمدة الإجابات. حدّث النموذج في الموبايل.');
    }

    public function section(SurveySettingsRequest $request, SurveyDesigner $designer): RedirectResponse
    {
        $designer->updateSection($request->user(), $request->validated());

        return to_route('heks3.settings')->with('status', 'تم تحديث القسم وترتيبه في المسودة.');
    }
}
