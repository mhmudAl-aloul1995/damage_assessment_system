<?php

declare(strict_types=1);

use App\Models\CsoAuditLog;
use App\Models\CsoSurvey;
use App\Models\User;
use App\services\ArcgisService;
use App\services\CsoAuditService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Spatie\Permission\Models\Role;

it('reports an uncertain ArcGIS result and rolls back local changes on connection failure', function (): void {
    Queue::fake();
    Http::preventStrayRequests();
    config(['services.arcgis.cso_survey_layer_url' => 'https://cso.test/FeatureServer']);
    $this->mock(ArcgisService::class)->shouldReceive('getToken')->andReturn('test-token');
    $user = User::factory()->create();
    $user->assignRole(Role::findOrCreate('Database Officer', 'web'));
    $survey = CsoSurvey::query()->create(['objectid' => 101, 'globalid' => 'failed-survey', 'building_name' => 'Original']);
    Http::fake([
        'https://cso.test/FeatureServer/0?*' => Http::response(['objectIdField' => 'OBJECTID', 'fields' => [['name' => 'building_name', 'type' => 'esriFieldTypeString']]]),
        'https://cso.test/FeatureServer/0/query?*' => Http::response(['features' => [['attributes' => ['building_name' => 'Original']]]]),
        'https://cso.test/FeatureServer/0/updateFeatures' => Http::failedConnection(),
    ]);
    $this->actingAs($user)->postJson(route('inf-audit.cso.field-update', $survey), [
        'table_type' => 'cso_survey_table', 'auditable_id' => $survey->id, 'field_name' => 'building_name', 'field_value' => 'New value',
    ])->assertStatus(502)->assertJsonPath('message', 'تعذر تأكيد حفظ التعديل في ArcGIS. لم يتم حفظ التعديل محلياً؛ تحقق من ArcGIS قبل إعادة المحاولة.');
    expect($survey->fresh()->building_name)->toBe('Original')->and(CsoAuditLog::query()->count())->toBe(0);
    Queue::assertNothingPushed();
});

it('does not contact ArcGIS when writing the local log fails', function (): void {
    Http::preventStrayRequests();
    config(['services.arcgis.cso_survey_layer_url' => 'https://cso.test/FeatureServer']);
    $this->mock(ArcgisService::class)->shouldReceive('getToken')->andReturn('test-token');
    Http::fake(function (Request $request) {
        return str_contains($request->url(), '/query')
            ? Http::response(['features' => [['attributes' => ['building_name' => 'Original']]]])
            : Http::response(['objectIdField' => 'OBJECTID', 'fields' => [['name' => 'building_name', 'type' => 'esriFieldTypeString']]]);
    });
    $survey = CsoSurvey::query()->create(['objectid' => 101, 'globalid' => 'log-failure-survey', 'building_name' => 'Original']);

    expect(fn () => app(CsoAuditService::class)->save($survey, $survey, 'cso_survey_table', 'building_name', 'Changed', 999999))
        ->toThrow(\Illuminate\Database\QueryException::class);
    expect($survey->fresh()->building_name)->toBe('Original')->and(CsoAuditLog::query()->count())->toBe(0);
    Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), '/updateFeatures'));
});

it('restores the previous ArcGIS value when local completion fails after remote success', function (bool $restoreSucceeds): void {
    Http::preventStrayRequests();
    config(['services.arcgis.cso_survey_layer_url' => 'https://cso.test/FeatureServer']);
    $this->mock(ArcgisService::class)->shouldReceive('getToken')->andReturn('test-token');
    $writes = [];
    Http::fake(function (Request $request) use (&$writes, $restoreSucceeds) {
        if (str_contains($request->url(), '/updateFeatures')) {
            $writes[] = json_decode($request['features'], true)[0]['attributes']['building_name'];

            return Http::response(['updateResults' => [['success' => count($writes) === 1 || $restoreSucceeds]]]);
        }

        return str_contains($request->url(), '/query')
            ? Http::response(['features' => [['attributes' => ['building_name' => 'Remote original']]]])
            : Http::response(['objectIdField' => 'OBJECTID', 'fields' => [['name' => 'building_name', 'type' => 'esriFieldTypeString']]]);
    });
    $user = User::factory()->create();
    $survey = CsoSurvey::query()->create(['objectid' => 101, 'globalid' => 'compensated-survey', 'building_name' => 'Original']);
    $connection = $survey->getConnection();
    $failingConnection = Mockery::mock($connection);
    $failingConnection->shouldReceive('transaction')->once()->andReturnUsing(function (Closure $callback) use ($connection): void {
        $connection->transaction(function () use ($callback): void {
            $callback();
            throw new RuntimeException('Local transaction could not complete.');
        });
    });
    $resolver = Mockery::mock(\Illuminate\Database\ConnectionResolverInterface::class);
    $resolver->shouldReceive('connection')->andReturn($failingConnection);
    \Illuminate\Database\Eloquent\Model::setConnectionResolver($resolver);

    try {
        app(CsoAuditService::class)->save($survey, $survey, 'cso_survey_table', 'building_name', 'Changed', $user->id);
        $this->fail('The local failure must be reported.');
    } catch (\Symfony\Component\HttpKernel\Exception\HttpException $exception) {
        expect($exception->getStatusCode())->toBe($restoreSucceeds ? 500 : 502);
    } finally {
        \Illuminate\Database\Eloquent\Model::setConnectionResolver(app('db'));
    }

    expect($writes)->toBe(['Changed', 'Remote original'])
        ->and($survey->fresh()->building_name)->toBe('Original')
        ->and(CsoAuditLog::query()->count())->toBe(0);
})->with([true, false]);
