<?php

declare(strict_types=1);

namespace App\Csp\Presets;

use Spatie\Csp\Directive;
use Spatie\Csp\Keyword;
use Spatie\Csp\Policy;
use Spatie\Csp\Preset;

class AppCspPreset implements Preset
{
    public function configure(Policy $policy): void
    {
        $policy
            ->add(Directive::BASE, Keyword::SELF)
            ->add(Directive::DEFAULT, Keyword::SELF)
            ->add(Directive::CONNECT, Keyword::SELF)
            ->add(Directive::FONT, Keyword::SELF)
            ->add(Directive::FONT, 'https://fonts.gstatic.com')
            ->add(Directive::FONT, 'data:')
            ->add(Directive::IMG, Keyword::SELF)
            ->add(Directive::IMG, 'data:')
            ->add(Directive::IMG, 'blob:')
            ->add(Directive::SCRIPT, Keyword::SELF)
            ->add(Directive::STYLE, Keyword::SELF)
            ->add(Directive::STYLE, 'https://fonts.googleapis.com')
            ->add(Directive::FRAME_ANCESTORS, Keyword::NONE)
            ->addNonce(Directive::SCRIPT)
            ->addNonce(Directive::STYLE);
    }
}
