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
        'heading' => 'Панель SSR',
        'description' => 'Онлайн-телеметрия пайплайна server-side рендеринга.',
        'summary' => [
            'heading' => 'Последняя сводка',
            'last_updated' => 'Последняя метрика: :timestamp',
            'metrics' => [
                'average_score' => 'Средний счёт',
                'path_count' => 'Отслеживаемых путей',
                'avg_html_size' => 'Средний размер HTML (КБ)',
                'avg_meta_tags' => 'Среднее число meta-тегов',
                'avg_og_tags' => 'Среднее число OG-тегов',
                'avg_ldjson_blocks' => 'Среднее число JSON-LD блоков',
                'avg_blocking_scripts' => 'Блокирующие скрипты',
                'avg_first_byte_ms' => 'Первый байт (мс)',
            ],
        ],
        'trend' => [
            'heading' => 'Тренд счёта',
            'description' => 'Средний SSR score по дням за последние :days дней.',
            'empty' => 'Данные тренда пока недоступны.',
            'columns' => [
                'date' => 'Дата',
                'score' => 'Счёт',
            ],
        ],
        'drops' => [
            'heading' => 'Наибольшие просадки',
            'description' => 'Пути с максимальным падением показателя день к дню.',
            'empty' => 'Сегодня просадок не обнаружено.',
            'columns' => [
                'path' => 'Путь',
                'yesterday' => 'Вчера',
                'today' => 'Сегодня',
                'delta' => 'Δ',
            ],
        ],
        'fallback' => [
            'heading' => 'Режим fallback',
            'description' => 'Метрики читаются из JSONL-файла, так как таблица базы данных недоступна.',
        ],
        'empty' => 'Метрики SSR ещё не записаны.',
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
