<?php

declare(strict_types=1);

return [
    'panel' => [
        'brand' => 'Аналитика',
        'navigation_group' => 'Аналитика',
        'navigation' => [
            'ctr' => 'CTR',
            'trends' => 'Тренды',
            'trends_advanced' => 'Тренды (расширенные)',
            'queue' => 'Очередь / Horizon',
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
        ],
        'ssr_stats' => [
            'label' => 'SSR Score',
            'description' => '{0}Нет путей|{1}:count путь|[2,4]:count пути|[5,*]:count путей',
        ],
        'ssr_drop' => [
            'heading' => 'Топ страниц по просадке SSR (день к дню)',
            'columns' => [
                'path' => 'Путь',
                'yesterday' => 'Вчера',
                'today' => 'Сегодня',
                'delta' => 'Δ',
            ],
            'empty' => 'Регрессий SSR за выбранный период не обнаружено.',
        ],
        'ssr_score' => [
            'heading' => 'Тренд SSR Score',
            'dataset' => 'SSR score',
            'date_column' => 'Дата',
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
