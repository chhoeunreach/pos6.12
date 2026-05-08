<?php

namespace Tests\Unit;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ManageLotRoutesTest extends TestCase
{
    public function test_manage_lot_routes_are_get_only(): void
    {
        $uris = [
            'manage-lot',
            'manage-lot/data',
            'manage-lot/{lot_id}/history',
            'manage-lot/{lot_id}/history/data',
        ];

        foreach ($uris as $uri) {
            $route = collect(Route::getRoutes()->getRoutes())
                ->first(fn ($r) => $r->uri() === $uri);

            $this->assertNotNull($route, 'Expected route "' . $uri . '" to exist.');
            $this->assertContains('GET', $route->methods());
            $this->assertNotContains('POST', $route->methods());
            $this->assertNotContains('PUT', $route->methods());
            $this->assertNotContains('PATCH', $route->methods());
            $this->assertNotContains('DELETE', $route->methods());
        }
    }
}

