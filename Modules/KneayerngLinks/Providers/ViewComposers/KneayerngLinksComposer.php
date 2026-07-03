<?php

namespace Modules\KneayerngLinks\Providers\ViewComposers;

use Illuminate\View\View;

class KneayerngLinksComposer
{
    public function compose(View $view): void
    {
        $links = [
            ['name' => 'POS', 'url' => 'https://pos.kneayerng.com'],
            ['name' => 'Accessory', 'url' => 'https://acc.kneayerng.com'],
            ['name' => 'Services', 'url' => 'https://ser.kneayerng.com'],
        ];
        
        $view->with('kneayerng_links', $links);
    }
}
