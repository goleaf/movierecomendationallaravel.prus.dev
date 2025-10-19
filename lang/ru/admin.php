<?php

return [
    'ctr' => [
        'title' => 'CTR аналитика',
        'period' => 'Период: :from — :to',
        'line_alt' => 'Линейный график CTR',
        'bars_alt' => 'Столбчатый график CTR',
        'ab_summary_heading' => 'Итоги A/B',
        'ab_summary_item' => 'Вариант :variant — Показы: :impressions, Клики: :clicks, CTR: :ctr%',
        'no_data' => 'Нет данных за выбранный период.',
        'filters' => [
            'from' => 'От даты',
            'to' => 'До даты',
            'placement' => 'Площадка',
            'variant' => 'Вариант',
            'placements' => [
                'all' => 'Все площадки',
                'home' => 'Главная',
                'show' => 'Страница фильма',
                'trends' => 'Страница трендов',
            ],
            'variants' => [
                'all' => 'Все варианты',
                'a' => 'Вариант A',
                'b' => 'Вариант B',
            ],
            'refresh' => 'Обновить аналитику',
        ],
        'charts' => [
            'daily_heading' => 'CTR по дням (A vs B)',
            'placements_heading' => 'CTR по площадкам',
        ],
        'placement_clicks' => [
            'heading' => 'Клики по площадкам',
            'placement' => 'Площадка',
            'clicks' => 'Клики',
        ],
        'funnels' => [
            'heading' => 'Воронки',
            'total' => 'Итого',
        ],
    ],
    'metrics' => [
        'title' => 'Очереди / Horizon',
        'heading' => 'Очереди',
        'stats' => 'Заданий: :jobs, Ошибок: :failed, Пакетов: :batches',
        'refresh' => 'Обновить статистику',
        'labels' => [
            'jobs' => 'Заданий в очереди',
            'failed' => 'Ошибок',
            'batches' => 'Пакеты',
        ],
        'horizon' => [
            'heading' => 'Horizon',
            'workload' => 'Нагрузка',
            'supervisors' => 'Супервайзеры',
            'empty' => 'Данные Horizon недоступны.',
        ],
    ],
    'funnel' => [
        'period' => 'Период: :from — :to',
        'headers' => [
            'placement' => 'Площадка',
            'imps' => 'Показы',
            'clicks' => 'Клики',
            'views' => 'Просмотры',
            'ctr' => 'CTR %',
            'view_rate' => 'Просмотр→Клик %',
        ],
    ],
    'trends' => [
        'filters' => [
            'days' => 'Дней',
            'type' => 'Тип',
            'genre' => 'Жанр',
            'year_from' => 'Год от',
            'year_to' => 'Год до',
        ],
        'days_option' => ':days дней',
        'type_placeholder' => 'Тип',
        'types' => [
            'movie' => 'Фильмы',
            'series' => 'Сериалы',
            'animation' => 'Мультфильмы',
        ],
        'genre_placeholder' => 'Жанр',
        'year_from_placeholder' => 'Год от',
        'year_to_placeholder' => 'Год до',
        'apply' => 'Показать',
        'period' => 'Топ за :days дн. (:from — :to)',
        'empty' => 'Нет данных по выбранным фильтрам.',
        'clicks' => 'Клики: :count',
        'imdb' => 'IMDb: :rating',
        'votes' => 'Голосов: :count',
    ],
];
