<?php

namespace Modules\LoanManagement\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class RecordLoanActivity
{
    protected string $connection = 'mysql_loan';

    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);
        $this->record($request, $response);

        return $response;
    }

    protected function record(Request $request, $response): void
    {
        try {
            if (! Schema::connection($this->connection)->hasTable('loan_activity_logs')) {
                return;
            }

            $route = $request->route();
            $routeName = $route ? (string) $route->getName() : null;
            $user = $request->user();
            $subject = $this->subjectFromRoute($request);
            $now = now();

            DB::connection($this->connection)->table('loan_activity_logs')->insert([
                'user_id' => $user->id ?? null,
                'user_name_snapshot' => $this->userName($user),
                'action' => $this->actionName($routeName, $request->method()),
                'method' => $request->method(),
                'route_name' => $routeName,
                'url' => Str::limit($request->fullUrl(), 500, ''),
                'source' => $this->sourceFromRoute($routeName),
                'subject_type' => $subject['type'],
                'subject_id' => $subject['id'],
                'response_status' => method_exists($response, 'getStatusCode') ? $response->getStatusCode() : null,
                'ip_address' => $request->ip(),
                'user_agent' => Str::limit((string) $request->userAgent(), 1000, ''),
                'request_payload_json' => $this->payloadJson($request),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    protected function actionName(?string $routeName, string $method): string
    {
        if (! $routeName) {
            return 'Loan Management '.$method;
        }

        return (string) Str::of($routeName)
            ->after('loan-management.')
            ->replace(['.', '-', '_'], ' ')
            ->title();
    }

    protected function sourceFromRoute(?string $routeName): ?string
    {
        if (! $routeName) {
            return null;
        }

        return Str::before(Str::after($routeName, 'loan-management.'), '.');
    }

    protected function subjectFromRoute(Request $request): array
    {
        foreach (['loan', 'payment', 'customer', 'location', 'batch', 'thread'] as $key) {
            $value = $request->route($key);
            $id = is_object($value) ? ($value->id ?? null) : $value;

            if ($id !== null && is_numeric($id)) {
                return ['type' => $key, 'id' => (int) $id];
            }
        }

        return ['type' => null, 'id' => null];
    }

    protected function payloadJson(Request $request): ?string
    {
        $payload = $request->except([
            '_token', '_method', 'password', 'password_confirmation', 'current_password',
        ]);

        foreach ($request->files->keys() as $key) {
            $payload[$key] = '[uploaded file]';
        }

        if (empty($payload)) {
            return null;
        }

        return json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    protected function userName($user): ?string
    {
        if (! $user) {
            return null;
        }

        $name = trim(implode(' ', array_filter([
            $user->first_name ?? null,
            $user->last_name ?? null,
        ])));

        return $name ?: ($user->username ?? $user->name ?? $user->email ?? null);
    }
}
