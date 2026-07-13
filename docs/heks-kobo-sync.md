# HEKS Kobo Sync

## Architecture

HEKS Kobo data enters through one normalized processing path:

- `POST /api/kobo/{service}` stores and synchronously syncs legacy REST submissions.
- `POST /api/heks/kobo-webhook/{service}` stores and queues HEKS webhook submissions.
- `kobo:sync-rest-submissions` replays stored submissions.
- `heks:kobo-mapping-report` reads paired Kobo `False` and `labels` exports and creates review reports.

Supported canonical services are configured in `config/heks_kobo.php`:

- `heks_main`
- `heks_followup`
- `heks_boq`
- `heks_followup_boq`

Legacy aliases such as `heks-main`, `heks-followups`, `heks-boq`, and `heks-followup-boq` are still accepted.

## Database

The hardening migration adds:

- Composite uniqueness for Kobo REST submissions by `service_name` and `submission_uuid`.
- `source_record_key` and `raw_data` support for wide Kobo record tables.
- Mapping metadata columns: `data_type`, `mapping_status`, `confidence`, and `notes`.
- Import status/error report fields.
- Source keys on follow-ups, BOQ items, and attachments.

Wide record tables keep every Kobo field through dynamic columns and `raw_data`. Normalized tables are populated only by confirmed logic in the HEKS sync service.

## Field Mapping

Generate reports from paired Kobo exports:

```bash
php artisan heks:kobo-mapping-report ^
  --main-false="C:\Users\M2\Downloads\Heks_Final_V1_-_all_versions_-_False_-_2026-07-12-16-14-47.xlsx" ^
  --main-labels="C:\Users\M2\Downloads\Heks_Final_V1_-_all_versions_-_labels_-_2026-07-12-16-16-35.xlsx" ^
  --followup-false="C:\Users\M2\Downloads\تقرير_المتابعة_-هيكس_125_-_all_versions_-_False_-_2026-07-12-16-43-11.xlsx" ^
  --followup-labels="C:\Users\M2\Downloads\تقرير_المتابعة_-هيكس_125_-_all_versions_-_labels_-_2026-07-12-16-43-07.xlsx" ^
  --boq-false="C:\Users\M2\Downloads\HEKS_-__BOQ2_With_BNFs_Data_-_all_versions_-_False_-_2026-07-12-16-47-26.xlsx" ^
  --boq-labels="C:\Users\M2\Downloads\HEKS_-__BOQ2_With_BNFs_Data_-_all_versions_-_labels_-_2026-07-12-16-47-23.xlsx"
```

Outputs:

- `storage/app/heks/reports/heks_mapping_report.xlsx`
- `storage/app/heks/reports/heks_boq_mapping_report.xlsx`

## Webhook Setup

Use this URL in Kobo for queued HEKS submissions:

```text
/api/heks/kobo-webhook/{service}
```

Send the shared secret in:

```text
X-Kobo-Token: {KOBO_REST_SERVICE_TOKEN}
```

The webhook returns `202 Accepted` and queues `SyncHeksKoboSubmission` on the `heks` queue.

## Environment

```env
KOBO_REST_SERVICE_TOKEN=
KOBOTOOLBOX_TOKEN=
HEKS_KOBO_QUEUE=heks
```

## Operations

```bash
php artisan migrate
php artisan optimize:clear
php artisan queue:work --queue=heks,default --tries=5 --timeout=0
php artisan kobo:sync-rest-submissions --all
php artisan heks:kobo-verify-fields
php artisan heks:import-followup-boqs --force
php artisan test --compact --filter=Heks
```

## Idempotency

- Submissions are keyed by `service_name + submission_uuid`.
- Wide records update by `submission_uuid` inside the service-specific wide table.
- Existing normalized sync logic uses `updateOrCreate` for beneficiaries, follow-ups, labels, BOQ rows, and attachments.
- Empty incoming values are not used to clear existing normalized values unless explicit clearing is implemented for a configured field.

## Rollback

1. Stop the queue worker.
2. Run `php artisan migrate:rollback --step=1` for the hardening migration if it has not been followed by later migrations.
3. Restore the previous queue/webhook route usage if Kobo was switched to the queued endpoint.
4. Re-run `php artisan optimize:clear`.

## Remaining Review Items

- Review low-confidence rows in `heks_mapping_report.xlsx` before promoting them to normalized mapping.
- Confirm BOQ item catalog links for rows in `heks_boq_mapping_report.xlsx`.
- Decide whether the legacy synchronous `/api/kobo/{service}` endpoint should later be converted to queued processing after downstream users are ready.
