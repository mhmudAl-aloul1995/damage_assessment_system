<?php

namespace App\Modules\Reh\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Reh\Http\Requests\RehPricingRequest;
use App\Modules\Reh\Models\RehCostEstimate;
use App\Modules\Reh\Models\RehPriceBook;
use App\Modules\Reh\Models\RehPriceItem;
use App\Modules\Reh\Models\RehUnit;
use App\Modules\Reh\Services\RehPriceImport;
use App\Modules\Reh\Services\RehPricing;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\View\View;

class RehPricingController extends Controller
{
    public function __construct(private RehPricing $pricing) {}

    public function index(Request $request): View|JsonResponse
    {
        abort_unless($this->pricing->ready(), 503, 'يلزم تشغيل ترحيل جداول التسعير أولًا.');
        $books = RehPriceBook::query()->latest('id')->get();
        $book = $request->integer('book') ? $books->firstWhere('id', $request->integer('book')) : $books->first();
        $items = $book?->items()->orderBy('id')->get() ?? collect();
        $previous = $book ? RehPriceBook::query()->where('status', 'approved')->where('id', '!=', $book->id)->latest('approved_at')->first() : null;
        $previousPrices = $previous?->items()->get()->keyBy('field') ?? collect();
        $logs = $book?->logs()->with('user:id,name')->latest('id')->limit(50)->get() ?? collect();
        $data = compact('books', 'book', 'items', 'previous', 'previousPrices', 'logs');

        return $request->expectsJson() ? response()->json(['html' => view('reh::pricing.content', $data)->render()])->header('Cache-Control', 'private, no-store') : view('reh::pricing.index', $data);
    }

    public function preview(RehPricingRequest $request, RehPriceImport $import): JsonResponse
    {
        try {
            $preview = $import->read($request->file('file')->getRealPath());
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            report($e);
            throw \Illuminate\Validation\ValidationException::withMessages(['file' => 'تعذر قراءة الملف. تحقق من مطابقته لقالب تحليل الأسعار.']);
        }
        $preview['filename'] = basename($request->file('file')->getClientOriginalName());
        $request->file('file')->storeAs('reh-price-sources', $preview['hash'].'.xlsx', 'local');
        $token = (string) Str::uuid();
        Cache::put('reh:price-preview:'.$request->user()->id.':'.$token, $preview, now()->addHour());

        return response()->json(['html' => view('reh::pricing.preview', compact('preview', 'token'))->render()])->header('Cache-Control', 'private, no-store');
    }

    public function store(RehPricingRequest $request): JsonResponse
    {
        $key = 'reh:price-preview:'.$request->user()->id.':'.$request->validated('token');

        return Cache::lock($key.':lock', 30)->block(3, function () use ($key, $request): JsonResponse {
            $preview = Cache::get($key);
            abort_unless($preview, 422, 'انتهت معاينة الملف؛ ارفعه مجددًا.');
            $book = $this->pricing->import($preview, $request->validated(), $request->user());
            Cache::forget($key);

            return response()->json(['message' => 'حُفظت مسودة الأسعار. راجع الملاحظات ثم اعتمد الإصدار.', 'book' => $book->id]);
        });
    }

    public function item(RehPriceItem $item): JsonResponse
    {
        $item->load(['components', 'book']);

        return response()->json(['html' => view('reh::pricing.item', compact('item'))->render()])->header('Cache-Control', 'private, no-store');
    }

    public function update(RehPricingRequest $request, RehPriceItem $item): JsonResponse
    {
        $this->pricing->updateItem($item, $request->validated(), $request->user());

        return response()->json(['message' => 'حُفظ التحليل وسُجل التعديل.', 'book' => $item->book_id]);
    }

    public function approve(RehPricingRequest $request, RehPriceBook $book): JsonResponse
    {
        $this->pricing->approveBook($book, $request->validated(), $request->user());

        return response()->json(['message' => 'اعتمد إصدار الأسعار.', 'book' => $book->id]);
    }

    public function updateBook(RehPricingRequest $request, RehPriceBook $book): JsonResponse
    {
        $this->pricing->updateBook($book, $request->validated(), $request->user());

        return response()->json(['message' => 'حُفظت إعدادات الإصدار وسُجل التغيير.', 'book' => $book->id]);
    }

    public function impact(RehPriceBook $book): JsonResponse
    {
        $previous = RehPriceBook::query()->where('status', 'approved')->where('id', '!=', $book->id)->latest('approved_at')->first();
        $changed = 0;
        $comparable = 0;
        $incomplete = 0;
        $difference = 0;
        if ($previous) {
            RehUnit::query()->whereHas('building')->with('building')->chunkById(200, function ($units) use ($book, $previous, &$changed, &$comparable, &$incomplete, &$difference): void {
                foreach ($units as $unit) {
                    $before = $this->pricing->calculate($unit, $previous);
                    $after = $this->pricing->calculate($unit, $book);
                    if (! $before['complete'] || ! $after['complete']) {
                        $incomplete++;

                        continue;
                    }
                    $comparable++;
                    $delta = (int) round(($after['usd'] - $before['usd']) * 100);
                    $difference += $delta;
                    if ($delta !== 0) {
                        $changed++;
                    }
                }
            });
        }
        $difference /= 100;

        return response()->json(['html' => view('reh::pricing.impact', compact('book', 'previous', 'changed', 'comparable', 'incomplete', 'difference'))->render()])->header('Cache-Control', 'private, no-store');
    }

    public function revise(RehPricingRequest $request, RehPriceBook $book): JsonResponse
    {
        $copy = $this->pricing->revise($book, $request->user(), $request->validated('reason'));

        return response()->json(['message' => 'أُنشئت مسودة مراجعة مستقلة.', 'book' => $copy->id]);
    }

    public function unit(Request $request, RehUnit $unit): JsonResponse
    {
        $book = $request->integer('book') ? RehPriceBook::query()->findOrFail($request->integer('book')) : ($this->pricing->latest() ?? ($this->pricing->ready() ? RehPriceBook::query()->latest('id')->first() : null));
        $calculation = $this->pricing->calculate($unit->load('building'), $book);
        $estimates = $this->pricing->ready() ? RehCostEstimate::query()->where('unit_id', $unit->id)->with(['book', 'approver:id,name', 'lines'])->latest('id')->get() : collect();
        $books = $this->pricing->ready() ? RehPriceBook::query()->latest('id')->get() : collect();

        return response()->json(['html' => view('reh::pricing.unit', compact('unit', 'book', 'calculation', 'estimates', 'books'))->render()])->header('Cache-Control', 'private, no-store');
    }

    public function approveEstimate(RehPricingRequest $request, RehUnit $unit): JsonResponse
    {
        $book = RehPriceBook::query()->findOrFail($request->validated('book_id'));
        $this->pricing->approveEstimate($unit, $book, $request->validated(), $request->user());

        return response()->json(['message' => 'حُفظت نسخة التكلفة المعتمدة مع الكميات والأسعار.']);
    }
}
