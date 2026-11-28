<?php

namespace Modules\Social\Tests\Unit;

use App\User;
use Illuminate\Http\Request;
use Modules\LoanManagement\Entities\LoanCustomer;
use Modules\Social\Http\Middleware\AuthenticateSocialActor;
use Modules\Social\Services\SocialActorResolver;
use Tests\TestCase;

class SocialActorResolverTest extends TestCase
{
    public function test_guest_request_resolves_to_null_and_middleware_returns_401(): void
    {
        $resolver = new SocialActorResolver();
        $request = Request::create('/api/social/posts/1/like', 'POST');

        $this->assertNull($resolver->resolve($request));

        $middleware = new AuthenticateSocialActor($resolver);
        $response = $middleware->handle($request, fn () => response()->json(['ok' => true]));

        $this->assertSame(401, $response->getStatusCode());
    }

    public function test_customer_and_staff_models_resolve_distinct_actor_types(): void
    {
        $resolver = new SocialActorResolver();

        $customer = new LoanCustomer();
        $customer->id = 42;
        $customer->name = 'Sokha';
        $customerRequest = Request::create('/api/social/posts/1/like', 'POST');
        $customerRequest->setUserResolver(fn () => $customer);

        $customerActor = $resolver->resolve($customerRequest);
        $this->assertIsArray($customerActor);
        $this->assertSame('customer', $customerActor['type']);
        $this->assertSame(42, $customerActor['id']);
        $this->assertSame('Sokha', $customerActor['name']);

        $staff = new User();
        $staff->id = 7;
        $staff->first_name = 'Admin';
        $staff->last_name = 'KneaYerng';
        $staffRequest = Request::create('/api/social/posts/1/like', 'POST');
        $staffRequest->setUserResolver(fn () => $staff);

        $staffActor = $resolver->resolve($staffRequest);
        $this->assertIsArray($staffActor);
        $this->assertSame('staff', $staffActor['type']);
        $this->assertSame(7, $staffActor['id']);
    }
}
