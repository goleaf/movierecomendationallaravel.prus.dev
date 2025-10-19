<?php echo '<?xml version="1.0" encoding="UTF-8"?>'.PHP_EOL; ?>
<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">
    <channel>
        <title>{{ $title }}</title>
        <link>{{ $link }}</link>
        <atom:link href="{{ $selfLink }}" rel="self" type="application/rss+xml" />
        <description>{{ $description }}</description>
        <language>{{ $language }}</language>
        @if($lastBuildDate)
            <lastBuildDate>{{ $lastBuildDate->format(DATE_RSS) }}</lastBuildDate>
        @endif
        @foreach($items as $item)
            <item>
                <title>{{ $item['title'] }}</title>
                <link>{{ $item['link'] }}</link>
                <guid isPermaLink="false">{{ $item['guid'] }}</guid>
                @if(!empty($item['description']))
                    <description><![CDATA[{!! $item['description'] !!}]]></description>
                @endif
                @if(isset($item['pubDate']) && $item['pubDate'] !== null)
                    <pubDate>{{ $item['pubDate']->format(DATE_RSS) }}</pubDate>
                @endif
                @if(!empty($item['categories']))
                    @foreach($item['categories'] as $category)
                        <category>{{ $category }}</category>
                    @endforeach
                @endif
                @if(!empty($item['enclosure']))
                    <enclosure url="{{ $item['enclosure']['url'] }}" type="{{ $item['enclosure']['type'] }}"@if(isset($item['enclosure']['length'])) length="{{ $item['enclosure']['length'] }}"@endif />
                @endif
            </item>
        @endforeach
    </channel>
</rss>
