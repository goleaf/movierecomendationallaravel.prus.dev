<?php

declare(strict_types=1);

return [
    'panel' => [
        'brand' => 'Аналитика',
        'navigation_group' => 'Аналитика',
        'navigation' => [
            'ctr' => 'CTR',
            'trends' => 'Тренды',
            'queue' => 'Очередь / Horizon',
            'ssr' => 'SSR',
        ],
    ],
    'widgets' => [
        'funnel' => [
            'heading' => 'Воронки (7 дней)',
            'period' => 'Период: :from — :to',
            'columns' => [
                'placement' => 'Placement',
                'imps' => 'Показы',
                'clicks' => 'Клики',
                'views' => 'Просмотры',
                'ctr' => 'CTR %',
                'cuped_ctr' => 'CTR (CUPED) %',
                'view_rate' => 'View→Click %',
            ],
            'placements' => [
                'home' => 'home',
                'show' => 'show',
                'trends' => 'trends',
            ],
            'total' => 'Итого',
        ],
        'queue_stats' => [
            'jobs' => [
                'label' => 'Задач в очереди',
                'description' => 'В очереди: :count',
            ],
            'failed' => [
                'label' => 'Ошибок',
                'description' => 'Ошибок: :count',
            ],
            'batches' => [
                'label' => 'Пакеты',
                'description' => 'Пакетов: :count',
            ],
        ],
        'z_test' => [
            'ctr_a' => 'CTR A',
            'ctr_b' => 'CTR B',
            'z_test' => 'Z-test',
            'impressions' => 'Показы: :count',
            'clicks' => 'Клики: :count',
            'description_format' => ':impressions · :clicks',
            'p_value' => [
                'significant' => 'p < 0.05',
                'not_significant' => 'p ≥ 0.05',
            ],
            'guard_rails' => [
                'minimum_samples' => 'Нужно минимум :min показов на вариант, чтобы оценить значимость.',
            ],
        ],
        'ssr_stats' => [
            'label' => 'SSR Score',
            'paths' => '{0}Нет путей|{1}:count путь|[2,4]:count пути|[5,*]:count путей',
            'samples' => '{0}Нет замеров|{1}:count замер|[2,4]:count замера|[5,*]:count замеров',
            'first_byte' => 'Первый байт: :value мс',
            'delta' => [
                'score' => 'Δ оценка: :value',
                'first_byte' => 'Δ первый байт: :value мс',
                'paths' => 'Δ пути: :value',
                'samples' => 'Δ замеры: :value',
            ],
            'periods' => [
                'today' => [
                    'label' => 'Сегодня',
                ],
                'yesterday' => [
                    'label' => 'Вчера',
                ],
                'seven_days' => [
                    'label' => 'Последние 7 дней',
                    'range' => ':from — :to',
                ],
            ],
        ],
        'ssr_drop' => [
            'heading' => 'Топ страниц по просадке SSR (день к дню)',
            'columns' => [
                'path' => 'Путь',
                'yesterday' => 'Вчера',
                'today' => 'Сегодня',
                'delta' => 'Δ',
            ],
        ],
        'ssr_score' => [
            'heading' => 'Тренд SSR Score (дневной и 7-дневный)',
            'datasets' => [
                'daily' => 'Среднее за день',
                'rolling' => 'Среднее за 7 дней',
            ],
        ],
        'images' => [
            'ctr_line_alt' => 'График CTR (линии)',
            'ctr_bars_alt' => 'График CTR (столбцы)',
        ],
    ],
    'svg' => [
        'ctr_line_title' => 'CTR по дням: A (синяя) vs B (зелёная)',
        'ctr_bars_title' => 'CTR по площадкам (A — синий, B — зелёный)',
    ],
    'hints' => [
        'ssr' => [
            'add_defer' => 'Добавьте defer к скриптам',
            'add_json_ld' => 'Добавьте JSON-LD',
            'expand_og' => 'Расширьте OG-теги',
            'reduce_payload' => 'Уменьшите HTML/изображения',
            'missing_json_ld' => 'Нет JSON-LD',
            'add_og' => 'Добавьте OG-теги',
        ],
    ],
];
