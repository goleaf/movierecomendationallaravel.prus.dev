<?php

return [
    'ctr' => [
        'title' => 'CTR аналитика',
        'period' => 'Период: :from — :to',
        'line_alt' => 'Линейный график CTR',
        'bars_alt' => 'Столбчатый график CTR',
        'ab_summary_heading' => 'Итоги A/B',
        'ab_summary_item' => 'Вариант :variant — Показы: :impressions, Клики: :clicks, CTR: :ctr%',
    ],
    'metrics' => [
        'title' => 'Очереди / Horizon',
        'heading' => 'Очереди',
        'stats' => 'Заданий: :jobs, Ошибок: :failed, Пакетов: :batches',
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
    ],
];
