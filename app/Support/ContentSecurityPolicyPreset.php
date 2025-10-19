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
            ->add(Directive::DEFAULT, Keyword::SELF)
            ->add(Directive::BASE, Keyword::SELF)
            ->add(Directive::SCRIPT, [
                Keyword::SELF,
                'https:',
            ])
            ->addNonce(Directive::SCRIPT)
            ->add(Directive::STYLE, [
                Keyword::SELF,
                'https:',
            ])
            ->addNonce(Directive::STYLE)
            ->add(Directive::IMG, [
                Keyword::SELF,
                'data:',
                'blob:',
                'https:',
            ])
            ->add(Directive::FONT, [
                Keyword::SELF,
                'data:',
                'https:',
            ])
            ->add(Directive::CONNECT, [
                Keyword::SELF,
                'https:',
            ])
            ->add(Directive::MEDIA, [
                Keyword::SELF,
                'https:',
            ])
            ->add(Directive::MANIFEST, Keyword::SELF)
            ->add(Directive::WORKER, [
                Keyword::SELF,
                'blob:',
            ])
            ->add(Directive::FRAME_ANCESTORS, Keyword::NONE)
            ->add(Directive::OBJECT, Keyword::NONE);
    }
}
