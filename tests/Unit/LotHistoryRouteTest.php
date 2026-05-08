<?php

namespace Tests\Unit;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class LotHistoryRouteTest extends TestCase
{
    public function test_lot_history_route_is_get_only(): void
    {
        $route = collect(Route::getRoutes()->getRoutes())
            ->first(fn ($r) => $r->uri() === 'reports/lot-history');

        $this->assertNotNull($route, 'Expected route "reports/lot-history" to exist.');
        $this->assertContains('GET', $route->methods());
        $this->assertNotContains('POST', $route->methods());
        $this->assertNotContains('PUT', $route->methods());
        $this->assertNotContains('PATCH', $route->methods());
        $this->assertNotContains('DELETE', $route->methods());
    }
}

