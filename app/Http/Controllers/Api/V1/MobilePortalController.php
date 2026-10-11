<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\MobileFormDiscardRequest;
use App\Http\Requests\Api\MobileFormSubmitRequest;
use App\Http\Requests\Api\MobileFormUploadRequest;
use App\Http\Requests\Api\MobilePortalQueryRequest;
use App\Models\MobileFormAttachment;
use App\Models\MobileFormSubmission;
use App\services\MobileFormService;
use App\services\MobilePortalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MobilePortalController extends Controller
{
    public function __construct(private MobilePortalService $portal, private MobileFormService $forms) {}

    public function catalog(Request $request): JsonResponse
    {
        return $this->json(['data' => $this->portal->catalog($request->user())]);
    }

    public function records(MobilePortalQueryRequest $request, string $module): JsonResponse
    {
        return $this->json($this->portal->records($request->user(), $module, $request->validated()));
    }

    public function show(Request $request, string $module, int $record): JsonResponse
    {
        return $this->json(['data' => $this->portal->detail($request->user(), $module, $record)]);
    }

    public function forms(Request $request): JsonResponse
    {
        return $this->json(['data' => $this->forms->catalog($request->user())]);
    }

    public function schema(MobilePortalQueryRequest $request, string $form): JsonResponse
    {
        $id = $request->validated('record');

        return $this->json(['data' => $this->forms->schema($request->user(), $form, $id !== null ? (int) $id : null)]);
    }

    public function formRecords(MobilePortalQueryRequest $request, string $form): JsonResponse
    {
        return $this->json($this->forms->records($request->user(), $form, $request->validated()));
    }

    public function submit(MobileFormSubmitRequest $request, string $form): JsonResponse
    {
        $result = $this->forms->submit($request->user(), $form, $request->validated(), $request);

        return $this->json($result, $result['replayed'] ? 200 : 201);
    }

    public function upload(MobileFormUploadRequest $request, string $form): JsonResponse
    {
        $attachment = $this->forms->upload($request->user(), $form, $request->validated('submission_uuid'), $request->file('file'));

        return $this->json(['data' => $attachment->only(['id', 'name', 'mime', 'size'])], 201);
    }

    public function submissions(MobilePortalQueryRequest $request): JsonResponse
    {
        $page = MobileFormSubmission::query()->where('user_id', $request->user()->id)->orderByDesc('id')->paginate(20, ['*'], 'page', (int) $request->validated('page', 1));
        $data = $page->getCollection()->filter(function (MobileFormSubmission $row) use ($request): bool {
            try {
                $this->forms->definition($request->user(), $row->form_key);

                return true;
            } catch (\Symfony\Component\HttpKernel\Exception\HttpException) {
                return false;
            }
        })->map(fn (MobileFormSubmission $row): array => $this->forms->receipt($row))->values()->all();

        return $this->json(['data' => $data, 'current_page' => $page->currentPage(), 'last_page' => $page->lastPage()]);
    }

    public function receipt(Request $request, string $uuid): JsonResponse
    {
        $row = MobileFormSubmission::query()->where('user_id', $request->user()->id)->where('submission_uuid', $uuid)->firstOrFail();
        $this->forms->authorizeReceipt($request->user(), $row);

        return $this->json(['data' => $this->forms->receipt($row)]);
    }

    public function discard(MobileFormDiscardRequest $request, string $form, int $attachment): JsonResponse
    {
        $this->forms->discard($request->user(), $form, $request->validated('submission_uuid'), $attachment);

        return $this->json(['discarded' => true]);
    }

    public function attachment(Request $request, int $attachment): StreamedResponse
    {
        $row = MobileFormAttachment::query()->where('user_id', $request->user()->id)->findOrFail($attachment);
        $this->forms->definition($request->user(), $row->form_key);
        if ($row->mobile_form_submission_id !== null) {
            $this->forms->authorizeReceipt($request->user(), $row->submission);
        }
        abort_unless(Storage::disk('local')->exists($row->path), 404);

        return Storage::disk('local')->response($row->path, $row->name, [
            'Content-Type' => $row->mime, 'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff', 'Content-Security-Policy' => "default-src 'none'; sandbox",
        ]);
    }

    /** @param array<string, mixed> $data */
    private function json(array $data, int $status = 200): JsonResponse
    {
        return response()->json($data, $status)->header('Cache-Control', 'private, no-store');
    }
}
