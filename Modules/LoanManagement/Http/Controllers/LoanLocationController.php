<?php

namespace Modules\LoanManagement\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class LoanLocationController extends Controller
{
    protected string $connection = 'mysql_loan';
    protected string $table = 'loan_business_locations';
    protected string $locationAssetRoot = 'loan_location_assets';

    public function index()
    {
        abort_if(! Schema::connection($this->connection)->hasTable($this->table), 404);
        $this->ensureTelegramChatColumns();

        $locations = DB::connection($this->connection)
            ->table($this->table)
            ->orderBy('name')
            ->get()
            ->map(function ($location) {
                $location->logo_asset_url = $this->assetUrl($location->logo_path ?? null);
                $location->payment_qr_asset_url = $this->assetUrl($location->payment_qr_path ?? null);
                $location->telegram_qr_asset_url = $this->assetUrl($location->telegram_qr_path ?? null);
                $location->telegram_payment_chat_id = $location->telegram_payment_chat_id ?? ($location->telegram_chat_id ?? null);
                $location->telegram_installment_chat_id = $location->telegram_installment_chat_id ?? ($location->telegram_chat_id ?? null);

                return $location;
            });

        return view('loanmanagement::locations.index', compact('locations'));
    }

    public function update(Request $request, int $location)
    {
        abort_if(! Schema::connection($this->connection)->hasTable($this->table), 404);
        $this->ensureTelegramChatColumns();

        $row = DB::connection($this->connection)->table($this->table)->where('id', $location)->first();
        abort_if(! $row, 404);

        $data = $request->validate([
            'logo' => 'nullable|image|max:4096',
            'payment_qr' => 'nullable|image|max:4096',
            'telegram_qr' => 'nullable|image|max:4096',
            'telegram_payment_chat_id' => 'nullable|string|max:191',
            'telegram_installment_chat_id' => 'nullable|string|max:191',
            'telegram_notify_payment' => 'nullable|boolean',
            'telegram_notify_installment' => 'nullable|boolean',
        ]);

        $payload = [];
        if ($request->hasFile('logo')) {
            $payload['logo_path'] = $this->storeLocationAsset($request, 'logo', $location);
        }
        if ($request->hasFile('payment_qr')) {
            $payload['payment_qr_path'] = $this->storeLocationAsset($request, 'payment_qr', $location);
        }
        if ($request->hasFile('telegram_qr')) {
            $payload['telegram_qr_path'] = $this->storeLocationAsset($request, 'telegram_qr', $location);
        }
        if ($request->has('telegram_payment_chat_id')) {
            $payload['telegram_payment_chat_id'] = trim((string) ($data['telegram_payment_chat_id'] ?? '')) ?: null;
        }
        if ($request->has('telegram_installment_chat_id')) {
            $payload['telegram_installment_chat_id'] = trim((string) ($data['telegram_installment_chat_id'] ?? '')) ?: null;
        }
        if ($request->has('telegram_payment_chat_id') || $request->has('telegram_installment_chat_id')) {
            $payload['telegram_chat_id'] = $payload['telegram_payment_chat_id']
                ?? $payload['telegram_installment_chat_id']
                ?? ($row->telegram_chat_id ?? null);
        }
        if ($request->has('telegram_notify_payment') || $request->has('telegram_payment_chat_id')) {
            $payload['telegram_notify_payment'] = $request->boolean('telegram_notify_payment');
        }
        if ($request->has('telegram_notify_installment') || $request->has('telegram_installment_chat_id')) {
            $payload['telegram_notify_installment'] = $request->boolean('telegram_notify_installment');
        }

        if (! empty($payload)) {
            $payload['updated_at'] = now();
            DB::connection($this->connection)
                ->table($this->table)
                ->where('id', $location)
                ->update($this->safeColumns($payload));
        }

        return redirect()
            ->route('loan-management.locations.index')
            ->with('status', ['success' => 1, 'msg' => 'Location print assets updated']);
    }

    protected function storeLocationAsset(Request $request, string $field, int $location): string
    {
        $file = $request->file($field);
        $directory = $this->locationAssetDirectory($location);

        if (! File::isDirectory($directory)) {
            File::makeDirectory($directory, 0755, true);
        }

        $filename = $field.'_'.time().'_'.uniqid().'.'.$file->getClientOriginalExtension();
        $file->move($directory, $filename);

        return $this->locationAssetRoot.'/'.$location.'/'.$filename;
    }

    public function asset(int $location, string $filename)
    {
        $path = $this->moduleLocationAssetPath($location, $filename);
        abort_if(empty($path), 404);

        return response()->file($path);
    }

    protected function locationAssetDirectory(int $location): string
    {
        return base_path('Modules/LoanManagement/'.$this->locationAssetRoot.'/'.$location);
    }

    protected function moduleLocationAssetPath(int $location, string $filename): ?string
    {
        if ($location <= 0 || Str::contains($filename, ['/', '\\']) || $filename !== basename($filename)) {
            return null;
        }

        $path = $this->locationAssetDirectory($location).DIRECTORY_SEPARATOR.$filename;

        return File::isFile($path) ? $path : null;
    }

    protected function fileDataUri(?string $path): ?string
    {
        if (empty($path) || ! File::isFile($path)) {
            return null;
        }

        $mime = function_exists('mime_content_type') ? mime_content_type($path) : null;
        if (empty($mime) || ! Str::startsWith($mime, 'image/')) {
            $mime = 'image/'.strtolower(pathinfo($path, PATHINFO_EXTENSION) ?: 'jpeg');
        }

        return 'data:'.$mime.';base64,'.base64_encode((string) file_get_contents($path));
    }

    protected function ensureTelegramChatColumns(): void
    {
        if (! Schema::connection($this->connection)->hasColumn($this->table, 'telegram_payment_chat_id')) {
            Schema::connection($this->connection)->table($this->table, function ($table) {
                $table->string('telegram_payment_chat_id')->nullable()->after('telegram_chat_id');
            });
        }

        if (! Schema::connection($this->connection)->hasColumn($this->table, 'telegram_installment_chat_id')) {
            Schema::connection($this->connection)->table($this->table, function ($table) {
                $table->string('telegram_installment_chat_id')->nullable()->after('telegram_payment_chat_id');
            });
        }
    }

    protected function assetUrl(?string $path): ?string
    {
        $path = trim((string) $path);
        if ($path === '') {
            return null;
        }

        $path = str_replace('\\', '/', ltrim($path, '/'));

        if (Str::startsWith($path, ['http://', 'https://', '//'])) {
            return $path;
        }

        if (Str::startsWith($path, 'public/')) {
            $path = substr($path, 7);
        }

        if (preg_match('#^(?:uploads/)?loan_location_assets/(\d+)/([^/]+)$#', $path, $matches)) {
            return $this->fileDataUri($this->moduleLocationAssetPath((int) $matches[1], $matches[2]));
        }

        if (preg_match('#^loan-management/location-assets/(\d+)/([^/]+)$#', $path, $matches)) {
            return $this->fileDataUri($this->moduleLocationAssetPath((int) $matches[1], $matches[2]));
        }

        if (file_exists(public_path($path))) {
            return asset($path);
        }

        return null;
    }

    protected function safeColumns(array $payload): array
    {
        return array_intersect_key(
            $payload,
            array_flip(Schema::connection($this->connection)->getColumnListing($this->table))
        );
    }
}
