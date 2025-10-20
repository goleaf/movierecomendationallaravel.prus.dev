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
            'impressions' => 'Показы::count',
            'clicks' => 'Клики::count',
            'description_format' => ':impressions :clicks',
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
            'empty' => 'Данные SSR недоступны.',
            'summary' => '{0}Нет отслеживаемых путей за период :start — :end.|{1}Отслеживаем :paths путь по :samples измерениям за период :start — :end.|[2,4]Отслеживаем :paths пути по :samples измерениям за период :start — :end.|[5,*]Отслеживаем :paths путей по :samples измерениям за период :start — :end.',
            'periods' => [
                'today' => [
                    'label' => 'Сегодня',
                    'comparison' => 'к вчерашнему дню',
                ],
                'yesterday' => [
                    'label' => 'Вчера',
                    'comparison' => 'к позавчерашнему дню',
                ],
                'seven_days' => [
                    'label' => 'Последние 7 дней',
                    'comparison' => 'к прошлой неделе',
                ],
                'delta' => 'Δ :delta :comparison',
                'delta_unavailable' => 'Δ н/д',
                'samples' => '{0}Нет выборок|{1}:count выборка|[2,4]:count выборки|[5,*]:count выборок',
                'first_byte' => [
                    'label' => 'Время первого байта: :value мс',
                    'delta' => '(:delta мс :comparison)',
                ],
                'first_byte_unavailable' => 'Время первого байта не фиксируется',
                'range' => 'Период: :start → :end',
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
            'heading' => 'Тренд SSR Score',
            'dataset' => 'SSR score',
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
