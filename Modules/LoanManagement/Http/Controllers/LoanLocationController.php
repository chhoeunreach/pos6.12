<?php

namespace Modules\LoanManagement\Http\Controllers;

use Illuminate\Support\Facades\Cache;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Modules\LoanManagement\Services\LoanSyncFromPosService;
use ZipArchive;

class LoanLocationController extends Controller
{
    protected string $connection = 'mysql_loan';
    protected string $table = 'loan_business_locations';
    protected string $locationAssetRoot = 'loan_location_assets';
    protected static array $tableExistsCache = [];
    protected static array $columnListingCache = [];

    public function index(Request $request)
    {
        abort_if(! $this->tableExists($this->table), 404);
        $this->ensureLoanInvoicePrefixColumn();
        $this->ensureTelegramChatColumns();
        $this->ensureLocationCrudColumns();

        $filters = [
            'name' => trim((string) $request->input('name', '')),
            'location_code' => trim((string) $request->input('location_code', '')),
            'phone' => trim((string) $request->input('phone', '')),
            'status' => trim((string) $request->input('status', '')),
        ];

        $locations = DB::connection($this->connection)
            ->table($this->table)
            ->when($this->hasColumnCached($this->table, 'deleted_at'), fn ($query) => $query->whereNull('deleted_at'))
            ->when($filters['name'] !== '', function ($query) use ($filters) {
                $query->where('name', 'like', '%'.$filters['name'].'%');
            })
            ->when($filters['location_code'] !== '', function ($query) use ($filters) {
                $query->where('location_code', 'like', '%'.$filters['location_code'].'%');
            })
            ->when($filters['phone'] !== '', function ($query) use ($filters) {
                $query->where('phone', 'like', '%'.$filters['phone'].'%');
            })
            ->when(in_array($filters['status'], ['active', 'inactive'], true), function ($query) use ($filters) {
                $query->where('status', $filters['status']);
            })
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

        return view('loanmanagement::locations.index', compact('locations', 'filters'));
    }

    public function assetGalleryModal()
    {
        $assetGallery = Cache::remember('loan_management.location_asset_gallery', now()->addMinutes(5), fn () => $this->assetGallery());

        return view('loanmanagement::locations.partials.asset_gallery', compact('assetGallery'));
    }

    public function export()
    {
        abort_if(! $this->tableExists($this->table), 404);
        $this->ensureLoanInvoicePrefixColumn();
        $this->ensureTelegramChatColumns();
        $this->ensureLocationCrudColumns();

        $columns = ['name', 'location_code', 'loan_invoice_prefix', 'address', 'phone', 'telegram_number', 'status'];
        $rows = DB::connection($this->connection)
            ->table($this->table)
            ->when($this->hasColumnCached($this->table, 'deleted_at'), fn ($query) => $query->whereNull('deleted_at'))
            ->orderBy('name')
            ->get($columns);

        $handle = fopen('php://temp', 'r+');
        fputcsv($handle, $columns);
        foreach ($rows as $row) {
            fputcsv($handle, array_map(fn ($column) => $row->{$column} ?? '', $columns));
        }
        rewind($handle);
        $content = stream_get_contents($handle);
        fclose($handle);

        return Response::make((string) $content, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="loan-locations-'.now()->format('Ymd-His').'.csv"',
        ]);
    }

    public function template()
    {
        $columns = ['name', 'location_code', 'loan_invoice_prefix', 'address', 'phone', 'telegram_number', 'status'];
        $example = ['Phnom Penh Branch', 'PP01', 'KY', 'Street 271, Phnom Penh', '012345678', '0717221349', 'active'];

        $handle = fopen('php://temp', 'r+');
        fputcsv($handle, $columns);
        fputcsv($handle, $example);
        rewind($handle);
        $content = stream_get_contents($handle);
        fclose($handle);

        return Response::make((string) $content, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="loan-location-import-template.csv"',
        ]);
    }

    public function import(Request $request)
    {
        abort_if(! $this->tableExists($this->table), 404);
        $this->ensureLoanInvoicePrefixColumn();
        $this->ensureTelegramChatColumns();
        $this->ensureLocationCrudColumns();

        $data = $request->validate([
            'duplicate_mode' => 'nullable|in:skip,replace',
            'file' => 'required|file|max:20480|mimes:csv,txt,xlsx',
        ]);

        try {
            $duplicateMode = $data['duplicate_mode'] ?? 'skip';
            $rows = $this->readLocationImportFile($request->file('file'));
            $headers = array_shift($rows) ?: [];
            $headers = array_map([$this, 'normalizeLocationHeader'], $headers);

            if (empty($headers)) {
                return redirect()->route('loan-management.locations.index')->with('status', [
                    'success' => 0,
                    'msg' => 'Import failed: file does not contain a header row.',
                ]);
            }

            $imported = 0;
            $skipped = 0;
            $invalid = 0;

            foreach ($rows as $row) {
                if ($this->isEmptyImportRow($row)) {
                    continue;
                }

                $mapped = $this->combineLocationRow($headers, $row);
                $normalized = $this->normalizeLocationImportRow($mapped);
                $errors = $this->validateImportedLocationRow($normalized);

                if (! empty($errors)) {
                    $invalid++;
                    continue;
                }

                $existing = $this->findExistingLocation($normalized);
                if ($existing && $duplicateMode === 'skip') {
                    $skipped++;
                    continue;
                }

                $payload = $this->safeColumns(array_merge(
                    $this->locationPayload($normalized),
                    ['updated_at' => now()]
                ));

                if ($existing && $duplicateMode === 'replace') {
                    DB::connection($this->connection)
                        ->table($this->table)
                        ->where('id', $existing->id)
                        ->update($payload);
                } else {
                    $payload['created_at'] = now();
                    DB::connection($this->connection)->table($this->table)->insert($payload);
                }

                $imported++;
            }

            return redirect()->route('loan-management.locations.index')->with('status', [
                'success' => 1,
                'msg' => 'Location import completed. Imported: '.$imported.', Skipped: '.$skipped.', Invalid: '.$invalid.'.',
            ]);
        } catch (\Throwable $e) {
            return redirect()->route('loan-management.locations.index')->with('status', [
                'success' => 0,
                'msg' => 'Import failed: '.$e->getMessage(),
            ]);
        }
    }

    public function syncFromPos(LoanSyncFromPosService $syncService)
    {
        abort_if(! $this->tableExists($this->table), 404);
        $this->ensureLocationCrudColumns();

        $businessId = (int) session('user.business_id');
        $result = $syncService->syncBusinessLocations($businessId ?: null, auth()->id());
        $success = empty($result['reason']);

        $message = $success
            ? 'POS locations synced. Synced: '.($result['synced'] ?? 0).', Skipped: '.($result['skipped'] ?? 0).'.'
            : 'POS location sync failed: '.$result['reason'];

        return redirect()
            ->route('loan-management.locations.index')
            ->with('status', ['success' => $success ? 1 : 0, 'msg' => $message]);
    }

    public function store(Request $request)
    {
        abort_if(! $this->tableExists($this->table), 404);
        $this->ensureLoanInvoicePrefixColumn();
        $this->ensureTelegramChatColumns();
        $this->ensureLocationCrudColumns();

        $data = $this->validateLocation($request);
        $payload = $this->locationPayload($data);
        $payload['created_at'] = now();
        $payload['updated_at'] = now();

        DB::connection($this->connection)->table($this->table)->insert($this->safeColumns($payload));

        return redirect()
            ->route('loan-management.locations.index')
            ->with('status', ['success' => 1, 'msg' => 'Location added successfully.']);
    }

    public function updateDetails(Request $request, int $location)
    {
        abort_if(! $this->tableExists($this->table), 404);
        $this->ensureLoanInvoicePrefixColumn();
        $this->ensureTelegramChatColumns();
        $this->ensureLocationCrudColumns();

        $row = DB::connection($this->connection)->table($this->table)->where('id', $location)->first();
        abort_if(! $row, 404);

        $payload = $this->locationPayload($this->validateLocation($request));
        $payload['updated_at'] = now();

        DB::connection($this->connection)
            ->table($this->table)
            ->where('id', $location)
            ->update($this->safeColumns($payload));

        return redirect()
            ->route('loan-management.locations.index')
            ->with('status', ['success' => 1, 'msg' => 'Location updated successfully.']);
    }

    public function destroy(int $location)
    {
        abort_if(! $this->tableExists($this->table), 404);

        $query = DB::connection($this->connection)->table($this->table)->where('id', $location);
        abort_if(! $query->first(), 404);

        if ($this->hasColumnCached($this->table, 'deleted_at')) {
            $query->update($this->safeColumns([
                'status' => 'inactive',
                'deleted_at' => now(),
                'updated_at' => now(),
            ]));
        } else {
            $query->delete();
        }

        return redirect()
            ->route('loan-management.locations.index')
            ->with('status', ['success' => 1, 'msg' => 'Location deleted successfully.']);
    }

    public function update(Request $request, int $location)
    {
        abort_if(! $this->tableExists($this->table), 404);
        $this->ensureLoanInvoicePrefixColumn();
        $this->ensureTelegramChatColumns();
        $this->ensureLocationCrudColumns();

        $row = DB::connection($this->connection)->table($this->table)->where('id', $location)->first();
        abort_if(! $row, 404);

        $data = $request->validate([
            'logo' => 'nullable|image|max:4096',
            'payment_qr' => 'nullable|image|max:4096',
            'telegram_qr' => 'nullable|image|max:4096',
            'logo_existing' => 'nullable|string|max:500',
            'payment_qr_existing' => 'nullable|string|max:500',
            'telegram_qr_existing' => 'nullable|string|max:500',
            'loan_invoice_prefix' => 'nullable|string|max:50',
            'telegram_payment_chat_id' => 'nullable|string|max:191',
            'telegram_installment_chat_id' => 'nullable|string|max:191',
            'telegram_notify_payment' => 'nullable|boolean',
            'telegram_notify_installment' => 'nullable|boolean',
        ]);

        $payload = [];
        if ($request->hasFile('logo')) {
            $payload['logo_path'] = $this->storeLocationAsset($request, 'logo', $location);
        } elseif (! empty($data['logo_existing']) && $this->isAllowedGalleryAsset($data['logo_existing'])) {
            $payload['logo_path'] = $this->normalizeAssetPath($data['logo_existing']);
        }
        if ($request->hasFile('payment_qr')) {
            $payload['payment_qr_path'] = $this->storeLocationAsset($request, 'payment_qr', $location);
        } elseif (! empty($data['payment_qr_existing']) && $this->isAllowedGalleryAsset($data['payment_qr_existing'])) {
            $payload['payment_qr_path'] = $this->normalizeAssetPath($data['payment_qr_existing']);
        }
        if ($request->hasFile('telegram_qr')) {
            $payload['telegram_qr_path'] = $this->storeLocationAsset($request, 'telegram_qr', $location);
        } elseif (! empty($data['telegram_qr_existing']) && $this->isAllowedGalleryAsset($data['telegram_qr_existing'])) {
            $payload['telegram_qr_path'] = $this->normalizeAssetPath($data['telegram_qr_existing']);
        }
        if ($request->has('loan_invoice_prefix')) {
            $payload['loan_invoice_prefix'] = $this->cleanLoanInvoicePrefix($data['loan_invoice_prefix'] ?? null);
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

    public function testTelegram(Request $request, int $location)
    {
        abort_if(! $this->tableExists($this->table), 404);
        $this->ensureTelegramChatColumns();

        $row = DB::connection($this->connection)->table($this->table)->where('id', $location)->first();
        abort_if(! $row, 404);

        $data = $request->validate([
            'type' => 'required|in:payment,installment',
            'chat_id' => 'nullable|string|max:191',
        ]);

        $type = $data['type'];
        $chatId = trim((string) ($data['chat_id'] ?? ''));
        if ($chatId === '') {
            $chatId = $type === 'payment'
                ? (string) ($row->telegram_payment_chat_id ?? '')
                : (string) ($row->telegram_installment_chat_id ?? '');
        }
        $chatId = trim($chatId ?: (string) ($row->telegram_chat_id ?? ''));

        if ($chatId === '') {
            return response()->json([
                'success' => false,
                'msg' => 'Please enter a Telegram chat ID first.',
            ], 422);
        }

        $label = $type === 'payment' ? 'Payment' : 'Installment';
        $message = "Loan Management Telegram Test\n"
            ."Location: ".($row->name ?? ('#'.$location))."\n"
            ."Channel: ".$label."\n"
            ."Sent at: ".now()->format('Y-m-d H:i:s');

        try {
            $result = app(\Modules\NotificationCenter\Services\NotificationService::class)->sendToChat(
                $type === 'payment' ? 'loan_payment' : 'loan_installment',
                $chatId,
                $message,
                [
                    'reference_type' => 'loan_location_telegram_test',
                    'reference_id' => $location,
                    'reference_no' => $row->location_code ?? (string) $location,
                    'module_type' => $type,
                ]
            );

            if (! empty($result['success'])) {
                return response()->json([
                    'success' => true,
                    'msg' => 'Telegram test sent to '.$label.' chat.',
                ]);
            }

            return response()->json([
                'success' => false,
                'msg' => $result['error'] ?? $result['message'] ?? 'Telegram test failed.',
            ], 422);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'msg' => 'Telegram test failed: '.$e->getMessage(),
            ], 500);
        }
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
        return module_path('LoanManagement', $this->locationAssetRoot.'/'.$location);
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

    protected function tableExists(string $table): bool
    {
        if (! array_key_exists($table, self::$tableExistsCache)) {
            self::$tableExistsCache[$table] = Schema::connection($this->connection)->hasTable($table);
        }

        return self::$tableExistsCache[$table];
    }

    protected function columnListing(string $table): array
    {
        if (! array_key_exists($table, self::$columnListingCache)) {
            self::$columnListingCache[$table] = $this->tableExists($table)
                ? Schema::connection($this->connection)->getColumnListing($table)
                : [];
        }

        return self::$columnListingCache[$table];
    }

    protected function hasColumnCached(string $table, string $column): bool
    {
        return in_array($column, $this->columnListing($table), true);
    }

    protected function ensureTelegramChatColumns(): void
    {
        if (! $this->hasColumnCached($this->table, 'telegram_payment_chat_id')) {
            Schema::connection($this->connection)->table($this->table, function ($table) {
                $table->string('telegram_payment_chat_id')->nullable()->after('telegram_chat_id');
            });
            self::$columnListingCache[$this->table] = [];
        }

        if (! $this->hasColumnCached($this->table, 'telegram_installment_chat_id')) {
            Schema::connection($this->connection)->table($this->table, function ($table) {
                $table->string('telegram_installment_chat_id')->nullable()->after('telegram_payment_chat_id');
            });
            self::$columnListingCache[$this->table] = [];
        }
    }

    protected function ensureLoanInvoicePrefixColumn(): void
    {
        if (! $this->hasColumnCached($this->table, 'loan_invoice_prefix')) {
            Schema::connection($this->connection)->table($this->table, function ($table) {
                $table->string('loan_invoice_prefix', 50)->nullable()->after('location_code');
            });
            self::$columnListingCache[$this->table] = [];
        }
    }

    protected function ensureLocationCrudColumns(): void
    {
        $columns = [
            'address' => fn ($table) => $table->text('address')->nullable()->after('loan_invoice_prefix'),
            'phone' => fn ($table) => $table->string('phone', 50)->nullable()->after('address'),
            'telegram_number' => fn ($table) => $table->string('telegram_number', 50)->nullable()->after('phone'),
            'status' => fn ($table) => $table->string('status', 20)->default('active'),
        ];

        foreach ($columns as $column => $creator) {
            if (! $this->hasColumnCached($this->table, $column)) {
                Schema::connection($this->connection)->table($this->table, function ($table) use ($creator) {
                    $creator($table);
                });
                self::$columnListingCache[$this->table] = [];
            }
        }
    }

    protected function validateLocation(Request $request): array
    {
        return $request->validate([
            'name' => 'required|string|max:191',
            'location_code' => 'nullable|string|max:100',
            'loan_invoice_prefix' => 'nullable|string|max:50',
            'address' => 'nullable|string|max:2000',
            'phone' => 'nullable|string|max:50',
            'telegram_number' => 'nullable|string|max:50',
            'status' => 'nullable|in:active,inactive',
        ]);
    }

    protected function locationPayload(array $data): array
    {
        return [
            'name' => trim((string) ($data['name'] ?? '')),
            'location_code' => trim((string) ($data['location_code'] ?? '')) ?: null,
            'loan_invoice_prefix' => $this->cleanLoanInvoicePrefix($data['loan_invoice_prefix'] ?? null),
            'address' => trim((string) ($data['address'] ?? '')) ?: null,
            'phone' => trim((string) ($data['phone'] ?? '')) ?: null,
            'telegram_number' => trim((string) ($data['telegram_number'] ?? '')) ?: null,
            'status' => $data['status'] ?? 'active',
        ];
    }

    protected function cleanLoanInvoicePrefix($prefix): ?string
    {
        $prefix = trim((string) $prefix);
        $prefix = preg_replace('/\s+/', '', $prefix) ?: '';

        return $prefix !== '' ? mb_substr($prefix, 0, 50) : null;
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
            return route('loan-management.locations.assets.show', [
                'location' => (int) $matches[1],
                'filename' => $matches[2],
            ]);
        }

        if (preg_match('#^loan-management/location-assets/(\d+)/([^/]+)$#', $path, $matches)) {
            return route('loan-management.locations.assets.show', [
                'location' => (int) $matches[1],
                'filename' => $matches[2],
            ]);
        }

        if (file_exists(public_path($path))) {
            return asset($path);
        }

        return null;
    }

    protected function assetGallery(): array
    {
        $images = [];
        $seen = [];

        foreach ($this->galleryRoots() as $root) {
            if (! File::isDirectory($root['directory'])) {
                continue;
            }

            $files = collect(File::allFiles($root['directory']))
                ->filter(fn ($file) => $this->isImageExtension($file->getExtension()))
                ->sortByDesc(fn ($file) => $file->getMTime())
                ->take(80);

            foreach ($files as $file) {
                $relative = str_replace('\\', '/', $file->getRelativePathname());
                $path = trim($root['prefix'].'/'.$relative, '/');
                if (isset($seen[$path])) {
                    continue;
                }

                $url = $root['type'] === 'module'
                    ? ($this->fileDataUri($file->getPathname()) ?: route('loan-management.locations.assets.show', [
                        'location' => basename($file->getPath()),
                        'filename' => $file->getFilename(),
                    ]))
                    : asset($path);

                if (empty($url)) {
                    continue;
                }

                $seen[$path] = true;
                $images[] = [
                    'path' => $path,
                    'url' => $url,
                    'name' => $file->getFilename(),
                    'modified' => date('Y-m-d H:i', $file->getMTime()),
                ];
            }
        }

        usort($images, fn ($a, $b) => strcmp($b['modified'], $a['modified']));

        return array_slice($images, 0, 120);
    }

    protected function galleryRoots(): array
    {
        return [
            [
                'type' => 'module',
                'directory' => module_path('LoanManagement', $this->locationAssetRoot),
                'prefix' => $this->locationAssetRoot,
            ],
            [
                'type' => 'public',
                'directory' => public_path('uploads/imgs'),
                'prefix' => 'uploads/imgs',
            ],
            [
                'type' => 'public',
                'directory' => public_path('uploads/business_logos'),
                'prefix' => 'uploads/business_logos',
            ],
            [
                'type' => 'public',
                'directory' => public_path('uploads1/business_logos'),
                'prefix' => 'uploads1/business_logos',
            ],
            [
                'type' => 'public',
                'directory' => public_path('uploads1/img'),
                'prefix' => 'uploads1/img',
            ],
            [
                'type' => 'public',
                'directory' => public_path('uploads1/carousel_images'),
                'prefix' => 'uploads1/carousel_images',
            ],
        ];
    }

    protected function isAllowedGalleryAsset(string $path): bool
    {
        $path = $this->normalizeAssetPath($path);
        if ($path === '' || Str::contains($path, ['..', '\\'])) {
            return false;
        }

        if (preg_match('#^'.$this->locationAssetRoot.'/(\d+)/([^/]+)$#', $path, $matches)) {
            return ! empty($this->moduleLocationAssetPath((int) $matches[1], $matches[2]));
        }

        foreach ($this->galleryRoots() as $root) {
            if ($root['type'] !== 'public') {
                continue;
            }

            $prefix = trim($root['prefix'], '/').'/';
            if (! Str::startsWith($path, $prefix)) {
                continue;
            }

            $fullPath = public_path($path);
            $rootPath = realpath($root['directory']);
            $realPath = realpath($fullPath);

            return $rootPath !== false
                && $realPath !== false
                && Str::startsWith(str_replace('\\', '/', $realPath), rtrim(str_replace('\\', '/', $rootPath), '/').'/')
                && File::isFile($realPath)
                && $this->isImageExtension(pathinfo($realPath, PATHINFO_EXTENSION));
        }

        return false;
    }

    protected function normalizeAssetPath(string $path): string
    {
        $path = str_replace('\\', '/', trim($path));
        $path = ltrim($path, '/');

        return Str::startsWith($path, 'public/') ? substr($path, 7) : $path;
    }

    protected function isImageExtension(?string $extension): bool
    {
        return in_array(strtolower((string) $extension), ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp'], true);
    }

    protected function safeColumns(array $payload): array
    {
        return array_intersect_key(
            $payload,
            array_flip($this->columnListing($this->table))
        );
    }

    protected function readLocationImportFile($file): array
    {
        $extension = strtolower((string) $file->getClientOriginalExtension());

        return $extension === 'xlsx'
            ? $this->readLocationXlsx($file->getRealPath())
            : $this->readLocationCsv($file->getRealPath());
    }

    protected function readLocationCsv(string $path): array
    {
        $handle = fopen($path, 'r');
        if (! $handle) {
            throw new \RuntimeException('Unable to read uploaded file.');
        }

        $rows = [];
        while (($row = fgetcsv($handle)) !== false) {
            $rows[] = $row;
        }
        fclose($handle);

        return $rows;
    }

    protected function readLocationXlsx(string $path): array
    {
        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            throw new \RuntimeException('Unable to read XLSX file.');
        }

        $sharedStrings = $this->xlsxSharedStrings($zip);
        $sheetPath = $this->xlsxFirstWorksheetPath($zip);
        $xml = $zip->getFromName($sheetPath);
        $zip->close();

        if ($xml === false) {
            throw new \RuntimeException('Unable to locate worksheet.');
        }

        $reader = new \XMLReader();
        $reader->XML($xml);
        $rows = [];
        $currentRow = [];
        $currentCellRef = null;
        $currentCellType = null;
        $currentValue = '';

        while ($reader->read()) {
            if ($reader->nodeType === \XMLReader::ELEMENT && $reader->localName === 'row') {
                $currentRow = [];
            } elseif ($reader->nodeType === \XMLReader::ELEMENT && $reader->localName === 'c') {
                $currentCellRef = $reader->getAttribute('r');
                $currentCellType = $reader->getAttribute('t');
                $currentValue = '';
            } elseif ($reader->nodeType === \XMLReader::ELEMENT && in_array($reader->localName, ['v', 't'], true)) {
                $currentValue .= $reader->readString();
            } elseif ($reader->nodeType === \XMLReader::END_ELEMENT && $reader->localName === 'c') {
                $columnIndex = $this->xlsxColumnIndex((string) $currentCellRef);
                $value = $currentValue;
                if ($currentCellType === 's' && $value !== '') {
                    $value = $sharedStrings[(int) $value] ?? $value;
                }
                $currentRow[$columnIndex] = trim((string) $value);
            } elseif ($reader->nodeType === \XMLReader::END_ELEMENT && $reader->localName === 'row') {
                if (! empty($currentRow)) {
                    ksort($currentRow);
                    $maxColumn = max(array_keys($currentRow));
                    $rows[] = array_map(
                        fn ($index) => $currentRow[$index] ?? '',
                        range(0, $maxColumn)
                    );
                }
            }
        }
        $reader->close();

        return $rows;
    }

    protected function xlsxSharedStrings(ZipArchive $zip): array
    {
        $xml = $zip->getFromName('xl/sharedStrings.xml');
        if ($xml === false) {
            return [];
        }

        $reader = new \XMLReader();
        $reader->XML($xml);
        $strings = [];
        $text = '';
        $inside = false;

        while ($reader->read()) {
            if ($reader->nodeType === \XMLReader::ELEMENT && $reader->localName === 'si') {
                $inside = true;
                $text = '';
            } elseif ($inside && $reader->nodeType === \XMLReader::TEXT) {
                $text .= $reader->value;
            } elseif ($reader->nodeType === \XMLReader::END_ELEMENT && $reader->localName === 'si') {
                $strings[] = $text;
                $inside = false;
            }
        }
        $reader->close();

        return $strings;
    }

    protected function xlsxFirstWorksheetPath(ZipArchive $zip): string
    {
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if (is_string($name) && preg_match('#^xl/worksheets/sheet\d+\.xml$#', $name)) {
                return $name;
            }
        }

        return 'xl/worksheets/sheet1.xml';
    }

    protected function xlsxColumnIndex(string $cellRef): int
    {
        preg_match('/([A-Z]+)/', $cellRef, $matches);
        $letters = $matches[1] ?? 'A';
        $index = 0;
        foreach (str_split($letters) as $letter) {
            $index = ($index * 26) + (ord($letter) - 64);
        }

        return $index - 1;
    }

    protected function normalizeLocationHeader($header): string
    {
        $header = preg_replace('/^\xEF\xBB\xBF/', '', (string) $header);
        $header = strtolower(trim($header));
        $header = preg_replace('/[^a-z0-9]+/', '_', $header);

        $aliases = [
            'location_id' => 'location_code',
            'branch_name' => 'name',
            'invoice_prefix' => 'loan_invoice_prefix',
            'telegram' => 'telegram_number',
            'telegram_no' => 'telegram_number',
            'telegram_phone' => 'telegram_number',
        ];

        return $aliases[$header] ?? trim((string) $header, '_');
    }

    protected function combineLocationRow(array $headers, array $row): array
    {
        $data = [];
        foreach ($headers as $index => $header) {
            $data[$header] = trim((string) ($row[$index] ?? ''));
        }

        return $data;
    }

    protected function isEmptyImportRow(array $row): bool
    {
        return trim(implode('', $row)) === '';
    }

    protected function normalizeLocationImportRow(array $row): array
    {
        return [
            'name' => trim((string) ($row['name'] ?? '')),
            'location_code' => trim((string) ($row['location_code'] ?? '')) ?: null,
            'loan_invoice_prefix' => $this->cleanLoanInvoicePrefix($row['loan_invoice_prefix'] ?? null),
            'address' => trim((string) ($row['address'] ?? '')) ?: null,
            'phone' => trim((string) ($row['phone'] ?? '')) ?: null,
            'telegram_number' => trim((string) ($row['telegram_number'] ?? '')) ?: null,
            'status' => in_array(($row['status'] ?? 'active'), ['active', 'inactive'], true) ? $row['status'] : 'active',
        ];
    }

    protected function validateImportedLocationRow(array $row): array
    {
        $errors = [];
        if (empty($row['name'])) {
            $errors[] = 'name is required';
        }

        return $errors;
    }

    protected function findExistingLocation(array $row)
    {
        $query = DB::connection($this->connection)->table($this->table);
        if (Schema::connection($this->connection)->hasColumn($this->table, 'deleted_at')) {
            $query->whereNull('deleted_at');
        }

        if (! empty($row['location_code'])) {
            $existing = (clone $query)->where('location_code', $row['location_code'])->first();
            if ($existing) {
                return $existing;
            }
        }

        return (clone $query)->where('name', $row['name'])->first();
    }
}
