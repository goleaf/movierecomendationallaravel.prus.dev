<?php

declare(strict_types=1);

return [
    'analytics_tabs' => [
        'heading' => 'Обзор аналитики',
        'queue' => [
            'label' => 'Очереди',
        ],
        'ctr' => [
            'label' => 'Динамика CTR',
        ],
        'funnels' => [
            'label' => 'Воронки',
        ],
        'ssr' => [
            'label' => 'SSR метрики',
        ],
        'experiments' => [
            'label' => 'Эксперименты',
        ],
    ],
    'ctr' => [
        'title' => 'CTR аналитика',
        'period' => 'Период: :from — :to',
        'line_alt' => 'Линейный график CTR',
        'bars_alt' => 'Столбчатый график CTR',
        'ab_summary_heading' => 'Итоги A/B',
        'ab_summary_item' => 'Вариант :variant — Показы: :impressions, Клики: :clicks, CTR: :ctr%',
        'no_data' => 'Нет данных за выбранный период.',
        'noscript_notice' => 'Включите JavaScript, чтобы увидеть графики. Ключевые метрики доступны в таблицах ниже.',
        'filters' => [
            'aria_label' => 'Фильтры CTR аналитики',
            'from' => 'От даты',
            'to' => 'До даты',
            'placement' => 'Площадка',
            'variant' => 'Вариант',
            'variant_all' => 'Все варианты',
            'placement_all' => 'Все площадки',
            'apply' => 'Обновить аналитику',
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
        ],
        'charts' => [
            'daily_heading' => 'CTR по дням (A vs B)',
            'daily_description' => 'Линейный график, показывающий сравнение ежедневного CTR вариантов A и B.',
            'placements_heading' => 'CTR по площадкам',
            'placements_description' => 'Столбчатый график CTR по площадкам для вариантов A и B.',
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
        'empty_summary' => 'Нет данных для выбранных фильтров.',
    ],
    'metrics' => [
        'title' => 'Очереди / Horizon',
        'heading' => 'Очереди',
        'stats' => 'Заданий: :jobs, Ошибок: :failed, Пакетов: :batches',
        'refresh' => 'Обновить статистику',
        'queue_label' => 'Заданий в очереди',
        'failed_label' => 'Ошибок',
        'processed_label' => 'Обработано пакетов',
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
            'actions' => [
                'pause' => [
                    'label' => 'Поставить Horizon на паузу',
                    'confirm' => 'Приостановить всех воркеров Horizon?',
                    'success' => 'Очереди Horizon приостановлены.',
                ],
                'resume' => [
                    'label' => 'Возобновить Horizon',
                    'confirm' => 'Возобновить работу воркеров Horizon?',
                    'success' => 'Очереди Horizon возобновлены.',
                ],
                'failed' => 'Не удалось обновить состояние очередей Horizon.',
                'unauthorized' => 'У вас нет прав для управления очередями Horizon.',
                'unavailable' => 'Horizon не установлен или недоступен.',
            ],
        ],
        'horizon_workload' => 'Нагрузка Horizon',
        'horizon_supervisors' => 'Супервайзеры Horizon',
        'horizon_empty' => 'Данные Horizon недоступны.',
    ],
    'ssr' => [
        'title' => 'SSR аналитика',
        'headline' => [
            'heading' => 'Оценка SSR',
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
            'metrics' => [
                'first_byte' => 'Первый байт',
                'paths' => 'Пути',
                'samples' => 'Замеры',
                'paths_count' => '{0}Нет путей|{1}:count путь|[2,4]:count пути|[5,*]:count путей',
                'samples_count' => '{0}Нет замеров|{1}:count замер|[2,4]:count замера|[5,*]:count замеров',
            ],
            'deltas' => [
                'score' => 'Δ оценка: :value',
                'first_byte' => 'Δ первый байт: :value мс',
                'paths' => 'Δ пути: :value',
                'samples' => 'Δ замеры: :value',
            ],
        ],
        'trend' => [
            'heading' => 'Тренд SSR Score (дневной и 7-дневный)',
            'empty' => 'Нет данных по тренду SSR.',
            'aria_label' => 'Линейный график изменения SSR score.',
            'range' => '{0}Нет данных|{1}Последний :days день|[2,*]Последние :days дней',
        ],
        'drop' => [
            'heading' => 'Топ страниц по просадке SSR',
            'empty' => 'Просадок SSR за выбранный период не найдено.',
            'columns' => [
                'path' => 'Путь',
                'yesterday' => 'Вчера',
                'today' => 'Сегодня',
                'delta' => 'Δ',
            ],
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
            'cuped_ctr' => 'CTR (CUPED) %',
            'view_rate' => 'Просмотр→Клик %',
        ],
    ],
    'trends' => [
        'days_label' => 'Дней',
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
        'period' => 'Период: :from — :to (:days дн.)',
        'empty' => 'Нет трендовых тайтлов по выбранным фильтрам.',
        'clicks' => 'Клики: :count',
        'imdb' => 'IMDb: :rating',
        'votes' => 'Голоса: :count',
    ],
];
