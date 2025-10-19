<?php

declare(strict_types=1);

namespace App\Support\Security;

use Spatie\Csp\Directive;
use Spatie\Csp\Keyword;
use Spatie\Csp\Policy;
use Spatie\Csp\Preset;
use Spatie\Csp\Presets\Basic as BasicPreset;
use Spatie\Csp\Value;

class AppCspPreset implements Preset
{
    public function __construct(private BasicPreset $basicPreset) {}

    public function configure(Policy $policy): void
    {
        $this->basicPreset->configure($policy);

        $policy
            ->add(Directive::CONNECT, [
                Keyword::SELF,
                'https://api.themoviedb.org',
            ])
            ->add(Directive::FONT, [
                Keyword::SELF,
                'https://fonts.gstatic.com',
                'https://fonts.bunny.net',
            ])
            ->add(Directive::FRAME, [
                Keyword::SELF,
                'https://www.youtube.com',
                'https://player.vimeo.com',
            ])
            ->add(Directive::IMG, [
                Keyword::SELF,
                'data:',
                'https:',
            ])
            ->add(Directive::SCRIPT, [
                Keyword::SELF,
                'https://www.youtube.com',
                'https://player.vimeo.com',
            ])
            ->add(Directive::STYLE, [
                Keyword::SELF,
                'https://fonts.googleapis.com',
                'https://fonts.bunny.net',
            ])
            ->add(Directive::STYLE_ATTR, Keyword::UNSAFE_INLINE)
            ->add(Directive::UPGRADE_INSECURE_REQUESTS, Value::NO_VALUE)
            ->addNonce(Directive::SCRIPT)
            ->addNonce(Directive::STYLE);
    }
}
