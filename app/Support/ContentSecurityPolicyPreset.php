<?php

declare(strict_types=1);

namespace App\Support;

use Spatie\Csp\Directive;
use Spatie\Csp\Keyword;
use Spatie\Csp\Policy;
use Spatie\Csp\Preset;

class ContentSecurityPolicyPreset implements Preset
{
    public function configure(Policy $policy): void
    {
        $policy
            ->addDirective(Directive::DEFAULT, Keyword::SELF)
            ->addDirective(Directive::BASE, Keyword::SELF)
            ->addDirective(Directive::SCRIPT, [
                Keyword::SELF,
                Keyword::UNSAFE_INLINE,
                Keyword::UNSAFE_EVAL,
                'https:',
            ])
            ->addDirective(Directive::STYLE, [
                Keyword::SELF,
                Keyword::UNSAFE_INLINE,
                'https:',
            ])
            ->addDirective(Directive::IMG, [
                Keyword::SELF,
                'data:',
                'blob:',
                'https:',
            ])
            ->addDirective(Directive::FONT, [
                Keyword::SELF,
                'data:',
                'https:',
            ])
            ->addDirective(Directive::CONNECT, [
                Keyword::SELF,
                'https:',
            ])
            ->addDirective(Directive::MEDIA, [
                Keyword::SELF,
                'https:',
            ])
            ->addDirective(Directive::MANIFEST, Keyword::SELF)
            ->addDirective(Directive::WORKER, [
                Keyword::SELF,
                'blob:',
            ])
            ->addDirective(Directive::FRAME_ANCESTORS, Keyword::NONE)
            ->addDirective(Directive::OBJECT, Keyword::NONE);
    }
}
