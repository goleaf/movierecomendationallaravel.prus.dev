<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

class LoggingDocsTest extends TestCase
{
    public function test_documented_channels_are_registered(): void
    {
        $documentedChannels = array_keys(config('logging.log_map'));
        $configuredChannels = array_keys(config('logging.channels'));

        $this->assertNotEmpty($documentedChannels, 'logging.log_map should list at least one channel.');

        foreach ($documentedChannels as $channel) {
            $this->assertContains(
                $channel,
                $configuredChannels,
                sprintf('Log channel "%s" documented for README is not registered in logging.channels.', $channel),
            );
        }
    }

    public function test_readme_mentions_documented_channels(): void
    {
        $readme = (string) file_get_contents(base_path('README.md'));

        $this->assertStringContainsString('## Log Map', $readme);

        foreach (array_keys(config('logging.log_map')) as $channel) {
            $this->assertStringContainsString(
                sprintf('`%s`', $channel),
                $readme,
                sprintf('README is missing the `%s` channel entry.', $channel),
            );
        }
    }
}
