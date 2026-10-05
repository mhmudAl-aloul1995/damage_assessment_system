<!doctype html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <title>{{ $beneficiary->name ?: $beneficiary->code }} - HEKS</title>
    <style>
        @page { size: A4; margin: 14mm 12mm; }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            background: #f7f9fc;
            color: #1f2937;
            font-family: "DejaVu Sans", "Tahoma", "Arial", sans-serif;
            direction: rtl;
            font-size: 12px;
            line-height: 1.8;
        }
        .paper {
            width: 100%;
            background: linear-gradient(180deg, rgba(0, 158, 247, .055), #fff 150px);
            border: 1px solid #e5e7eb;
            border-radius: 14px;
            padding: 26px 30px;
        }
        .header {
            text-align: center;
            margin-bottom: 24px;
            padding-bottom: 16px;
            border-bottom: 1px solid #e5e7eb;
        }
        .title {
            color: #1b84ff;
            font-size: 26px;
            font-weight: 800;
            margin: 0 0 8px;
        }
        .meta {
            color: #6b7280;
            font-size: 11px;
        }
        .section {
            break-inside: avoid;
            margin-bottom: 22px;
            padding-top: 16px;
            border-top: 1px solid #e5e7eb;
        }
        .section:first-of-type {
            border-top: 0;
            padding-top: 0;
        }
        .section-title {
            position: relative;
            margin: 0 0 12px;
            padding-right: 14px;
            color: #111827;
            font-size: 16px;
            font-weight: 800;
        }
        .section-title::before {
            content: "";
            position: absolute;
            right: 0;
            top: 4px;
            width: 5px;
            height: 22px;
            border-radius: 10px;
            background: linear-gradient(180deg, #1b84ff, #50cd89);
        }
        .item {
            break-inside: avoid;
            padding: 11px 0;
            border-bottom: 1px dashed #d9e1eb;
        }
        .item:last-child {
            border-bottom: 0;
        }
        .question {
            margin-bottom: 7px;
            color: #1f2937;
            font-weight: 800;
        }
        .answer {
            min-height: 32px;
            display: flex;
            align-items: center;
            padding: 6px 10px;
            border: 1px solid #e5e7eb;
            border-bottom: 2px solid rgba(27, 132, 255, .28);
            border-radius: 8px 8px 5px 5px;
            background: rgba(248, 250, 252, .9);
            color: #374151;
            overflow-wrap: anywhere;
        }
        .answer.empty {
            color: transparent;
            background: #fff;
        }
        .choices {
            display: flex;
            flex-direction: column;
            gap: 7px;
            margin-top: 7px;
        }
        .choice {
            display: flex;
            align-items: flex-start;
            gap: 9px;
            padding: 7px 9px;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            background: #fff;
            color: #4b5563;
        }
        .choice.selected {
            border-color: rgba(27, 132, 255, .35);
            background: rgba(27, 132, 255, .08);
            color: #111827;
            font-weight: 800;
        }
        .marker {
            width: 16px;
            height: 16px;
            margin-top: 4px;
            flex: 0 0 16px;
            border: 1.4px solid #9ca3af;
            background: #fff;
        }
        .marker.radio {
            border-radius: 50%;
        }
        .marker.checkbox {
            border-radius: 3px;
        }
        .marker.selected {
            border-color: #1b84ff;
            background: #1b84ff;
        }
        .marker.selected::after {
            content: "";
            display: block;
            width: 7px;
            height: 7px;
            margin: 3.5px auto;
            background: #fff;
        }
        .marker.radio.selected::after {
            border-radius: 50%;
        }
        .footer {
            margin-top: 20px;
            color: #9ca3af;
            text-align: center;
            font-size: 10px;
        }
    </style>
</head>
<body>
    <main class="paper">
        <header class="header">
            <h1 class="title">{{ $beneficiary->name ?: $beneficiary->code }}</h1>
            <div class="meta">
                {{ $beneficiary->code ?? '-' }}
                @if ($beneficiary->identity_number)
                    | {{ $beneficiary->identity_number }}
                @endif
                @if ($beneficiary->responsibleEngineerName())
                    | {{ $beneficiary->responsibleEngineerName() }}
                @endif
            </div>
        </header>

        @forelse ($surveySections as $section)
            <section class="section">
                <h2 class="section-title">{{ $section['title'] }}</h2>

                @foreach ($section['items'] as $item)
                    @php
                        $fieldType = $item['field_type'] ?? null;
                        $hasChoices = !empty($item['choices']);
                        $markerType = $fieldType === 'select_multiple' ? 'checkbox' : 'radio';
                    @endphp
                    <div class="item">
                        <div class="question">{{ $item['question'] }}</div>

                        @if ($hasChoices && in_array($fieldType, ['select_one', 'select_multiple'], true))
                            <div class="choices">
                                @foreach ($item['choices'] as $choice)
                                    <div class="choice {{ $choice['selected'] ? 'selected' : '' }}">
                                        <span class="marker {{ $markerType }} {{ $choice['selected'] ? 'selected' : '' }}"></span>
                                        <span>{{ $choice['label'] }}</span>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <div class="answer {{ filled($item['value']) ? '' : 'empty' }}">{{ filled($item['value']) ? $item['value'] : '-' }}</div>
                        @endif
                    </div>
                @endforeach
            </section>
        @empty
            <section class="section">
                <h2 class="section-title">لا توجد بيانات استبيان</h2>
                <div class="answer">لا توجد إجابات KoBo مرتبطة بهذا المستفيد.</div>
            </section>
        @endforelse

        <footer class="footer">
            HEKS Shelter Repairs | {{ $generatedAt }}
        </footer>
    </main>
</body>
</html>
