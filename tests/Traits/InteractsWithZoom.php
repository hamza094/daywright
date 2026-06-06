<?php

declare(strict_types=1);

namespace Tests\Traits;

use App\Interfaces\Zoom;
use App\Services\Zoom\ZoomOAuthService;
use App\Services\Zoom\ZoomServiceFake;

trait InteractsWithZoom
{
    private function fakeZoom(): ZoomServiceFake
    {
        $zoomServiceFake = new ZoomServiceFake;

        $this->swap(Zoom::class, $zoomServiceFake);
        $this->swap(ZoomOAuthService::class, $zoomServiceFake);

        return $zoomServiceFake;
    }
}
