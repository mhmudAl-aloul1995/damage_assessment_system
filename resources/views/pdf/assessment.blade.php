<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>{{ $buildingTitle }}</title>
    <style>
        @page {
            margin: 22px 18px;
        }

        body {
            font-family: DejaVu Sans, sans-serif;
            color: #1f2937;
            font-size: 12px;
            line-height: 1.7;
            margin: 0;
            background: #ffffff;
        }

        .report-shell {
            width: 100%;
        }

        .report-header {
            background: linear-gradient(135deg, #0f766e 0%, #115e59 100%);
            color: #ffffff;
            border-radius: 14px;
            padding: 18px 22px 20px;
            margin-bottom: 18px;
        }

        .report-brand {
            text-align: center;
            margin-bottom: 14px;
        }

        .report-logo {
            max-width: 100%;
            width: 420px;
            max-height: 120px;
            object-fit: contain;
            display: inline-block;
        }

        .report-kicker {
            font-size: 11px;
            letter-spacing: 0.8px;
            opacity: 0.85;
            margin-bottom: 6px;
            text-align: center;
        }

        .report-title {
            font-size: 24px;
            font-weight: 700;
            margin: 0 0 8px;
            text-align: center;
        }

        .report-subtitle {
            font-size: 12px;
            opacity: 0.92;
            margin: 0;
            text-align: center;
        }

        .meta-grid {
            width: 100%;
            border-spacing: 10px;
            border-collapse: separate;
            margin-bottom: 14px;
        }

        .meta-card {
            width: 50%;
            background: #f8fafc;
            border: 1px solid #dbe4ea;
            border-radius: 12px;
            padding: 12px 14px;
            vertical-align: top;
        }

        .meta-label {
            display: block;
            color: #64748b;
            font-size: 10px;
            margin-bottom: 3px;
        }

        .meta-value {
            display: block;
            color: #0f172a;
            font-size: 14px;
            font-weight: 700;
        }

        .section {
            margin-top: 18px;
        }

        .section-header {
            background: #ecfeff;
            border: 1px solid #b6ece7;
            border-right: 5px solid #0f766e;
            border-radius: 10px;
            padding: 10px 14px;
            margin-bottom: 10px;
        }

        .section-title {
            margin: 0;
            color: #134e4a;
            font-size: 15px;
            font-weight: 700;
        }

        .section-note {
            margin: 4px 0 0;
            color: #4b5563;
            font-size: 10px;
        }

        table.assessment-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            margin-bottom: 14px;
            border: 1px solid #dbe4ea;
            border-radius: 12px;
            overflow: hidden;
        }

        .assessment-table thead th {
            background: #0f172a;
            color: #ffffff;
            font-size: 11px;
            font-weight: 700;
            padding: 11px 12px;
            text-align: right;
            border: 0;
        }

        .assessment-table tbody tr:nth-child(odd) {
            background: #ffffff;
        }

        .assessment-table tbody tr:nth-child(even) {
            background: #f8fafc;
        }

        .assessment-table tbody td {
            padding: 12px;
            vertical-align: top;
            border-top: 1px solid #e5edf3;
        }

        .question-cell {
            width: 58%;
        }

        .answer-cell {
            width: 42%;
        }

        .question-label {
            color: #111827;
            font-size: 12px;
            font-weight: 700;
            margin-bottom: 4px;
        }

        .question-hint {
            color: #6b7280;
            font-size: 10px;
        }

        .answer-badge {
            display: inline-block;
            max-width: 100%;
            background: #ecfdf5;
            color: #166534;
            border: 1px solid #bbf7d0;
            border-radius: 999px;
            padding: 5px 10px;
            font-size: 11px;
            font-weight: 700;
            word-break: break-word;
        }

        .answer-badge.is-empty {
            background: #f3f4f6;
            color: #6b7280;
            border-color: #e5e7eb;
        }

        .gallery-section {
            margin-top: 18px;
            page-break-inside: avoid;
        }

        .gallery-section.break-before {
            page-break-before: always;
        }

        .gallery-wrap {
            padding: 14px;
            background: #f8fafc;
            border: 1px solid #dbe4ea;
            border-radius: 12px;
        }

        .gallery-title {
            margin: 0 0 6px;
            color: #0f172a;
            font-size: 13px;
            font-weight: 700;
        }

        .gallery-note {
            margin: 0 0 12px;
            color: #64748b;
            font-size: 10px;
        }

        .gallery-grid {
            font-size: 0;
        }

        .gallery-item {
            display: inline-block;
            width: 48%;
            margin-left: 4%;
            margin-bottom: 14px;
            vertical-align: top;
            page-break-inside: avoid;
        }

        .gallery-item:nth-child(2n) {
            margin-left: 0;
        }

        .gallery-frame {
            border: 1px solid #dbe4ea;
            border-radius: 12px;
            background: #ffffff;
            overflow: hidden;
            box-shadow: 0 4px 10px rgba(15, 23, 42, 0.05);
        }

        .gallery-image {
            display: block;
            width: 100%;
            height: 200px;
            object-fit: cover;
            background: #e5e7eb;
        }

        .gallery-meta {
            padding: 10px 12px;
            font-size: 10px;
            color: #475569;
            word-break: break-word;
        }

        .gallery-name {
            font-size: 11px;
            font-weight: 700;
            color: #0f172a;
            margin-bottom: 4px;
        }

        .gallery-type {
            display: inline-block;
            background: #ecfeff;
            color: #0f766e;
            border: 1px solid #b6ece7;
            border-radius: 999px;
            padding: 3px 8px;
            font-size: 9px;
            font-weight: 700;
        }

        .footer-note {
            margin-top: 14px;
            text-align: center;
            color: #94a3b8;
            font-size: 10px;
        }
    </style>
</head>
<body>
    @php
        $logoPath = base_path('assets/media/logos/LogoGaza2.jpeg');
        $logoDataUri = file_exists($logoPath)
            ? 'data:image/jpeg;base64,' . base64_encode(file_get_contents($logoPath))
            : null;
    @endphp

    <div class="report-shell">
        <div class="report-header">
            @if ($logoDataUri)
                <div class="report-brand">
                    <img src="{{ $logoDataUri }}" alt="PHC Logo" class="report-logo">
                </div>
            @endif
            <div class="report-kicker">DAMAGE ASSESSMENT REPORT</div>
            <h1 class="report-title">{{ $buildingTitle }}</h1>
            <p class="report-subtitle">Assessment summary for the building and all linked housing units.</p>
        </div>

        <table class="meta-grid">
            <tr>
                <td class="meta-card">
                    <span class="meta-label">BUILDING ID</span>
                    <span class="meta-value">{{ $building->objectid ?? '-' }}</span>
                </td>
                <td class="meta-card">
                    <span class="meta-label">GLOBAL ID</span>
                    <span class="meta-value">{{ $building->globalid }}</span>
                </td>
            </tr>
        </table>

        <div class="section">
            <div class="section-header">
                <h2 class="section-title">Building Assessment</h2>
                <p class="section-note">Main assessment answers for the selected building.</p>
            </div>

            <table class="assessment-table">
                <thead>
                    <tr>
                        <th class="question-cell">Question</th>
                        <th class="answer-cell">Answer</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($buildingRows as $row)
                        <tr>
                            <td class="question-cell">
                                <div class="question-label">{{ $row['label'] }}</div>
                                @if (!empty($row['hint']))
                                    <div class="question-hint">{{ $row['hint'] }}</div>
                                @endif
                            </td>
                            <td class="answer-cell">
                                <span class="answer-badge {{ $row['answer'] === '-' ? 'is-empty' : '' }}">{{ $row['answer'] }}</span>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @if ($buildingAttachments->isNotEmpty())
            <div class="gallery-section break-before">
                <div class="section-header">
                    <h2 class="section-title">Building Attachments</h2>
                    <p class="section-note">Visual record of the building attachments included with the assessment.</p>
                </div>
                <div class="gallery-wrap">
                    <div class="gallery-grid">
                        @foreach ($buildingAttachments as $attachment)
                            <div class="gallery-item">
                                <div class="gallery-frame">
                                    <img src="{{ $attachment['url'] }}" alt="{{ $attachment['name'] }}" class="gallery-image">
                                    <div class="gallery-meta">
                                        <div class="gallery-name">{{ $attachment['name'] }}</div>
                                        <span class="gallery-type">{{ $attachment['content_type'] ?: 'Attachment' }}</span>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        @endif

        @foreach ($housingSections as $section)
            <div class="section">
                <div class="section-header">
                    <h2 class="section-title">{{ $section['title'] }}</h2>
                    <p class="section-note">Housing unit assessment details linked to this building.</p>
                </div>

                <table class="assessment-table">
                    <thead>
                        <tr>
                            <th class="question-cell">Question</th>
                            <th class="answer-cell">Answer</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($section['rows'] as $row)
                            <tr>
                                <td class="question-cell">
                                    <div class="question-label">{{ $row['label'] }}</div>
                                    @if (!empty($row['hint']))
                                        <div class="question-hint">{{ $row['hint'] }}</div>
                                    @endif
                                </td>
                                <td class="answer-cell">
                                    <span class="answer-badge {{ $row['answer'] === '-' ? 'is-empty' : '' }}">{{ $row['answer'] }}</span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>

                @if (collect($section['attachments'])->isNotEmpty())
                    <div class="gallery-section">
                        <div class="gallery-wrap">
                            <h3 class="gallery-title">Housing Unit Attachments</h3>
                            <p class="gallery-note">Supporting images linked to this housing unit.</p>
                            <div class="gallery-grid">
                                @foreach ($section['attachments'] as $attachment)
                                    <div class="gallery-item">
                                        <div class="gallery-frame">
                                            <img src="{{ $attachment['url'] }}" alt="{{ $attachment['name'] }}" class="gallery-image">
                                            <div class="gallery-meta">
                                                <div class="gallery-name">{{ $attachment['name'] }}</div>
                                                <span class="gallery-type">{{ $attachment['content_type'] ?: 'Attachment' }}</span>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                @endif
            </div>
        @endforeach

        <div class="footer-note">Generated from the assessment screen export.</div>
    </div>
</body>
</html>

