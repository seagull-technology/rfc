<?php

namespace Tests\Feature;

use Illuminate\Routing\Route as IlluminateRoute;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class GovernmentGatewayRoutePolicyTest extends TestCase
{
    public function test_public_route_paths_avoid_restricted_gateway_segments(): void
    {
        $restrictedSegment = '/(^|\/)(login|admin|user|users)(\/|$)/i';

        $violations = collect(Route::getRoutes())
            ->map(fn (IlluminateRoute $route): string => $route->uri())
            ->filter(fn (string $uri): bool => preg_match($restrictedSegment, $uri) === 1)
            ->values()
            ->all();

        $this->assertSame([], $violations);
    }

    public function test_key_named_routes_generate_neutral_paths(): void
    {
        $this->assertSame('/sign-in', route('login', [], false));
        $this->assertSame('/control-panel', route('admin.dashboard', [], false));
        $this->assertSame('/control-panel/accounts', route('admin.users.index', [], false));
        $this->assertSame('/company/team', route('company.employees.index', [], false));
    }
}
