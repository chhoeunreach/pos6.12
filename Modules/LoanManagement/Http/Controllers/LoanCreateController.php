<?php

namespace Modules\LoanManagement\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use App\Utils\TransactionUtil;
use Modules\LoanManagement\Http\Requests\StoreStandaloneLoanRequest;
use Modules\LoanManagement\Services\CreateStandaloneLoanService;
use Modules\LoanManagement\Services\KhmerNationalIdCard\GoogleVisionOcrService;
use Modules\LoanManagement\Services\OpenAiProductPhotoParserService;
use App\CustomerGroup;
use App\User;
use App\Utils\ModuleUtil;
use App\BusinessLocation;

class LoanCreateController extends Controller
{
    public function __construct(protected CreateStandaloneLoanService $service)
    {
    }

    public function index()
    {
        $business_id = session('user.business_id');

        $locations = DB::table('business_locations')->orderBy('name')->pluck('name', 'id');

        $permitted_locations = auth()->user()->permitted_locations($business_id);
        $defaultLocationId = null;
        if ($permitted_locations === 'all') {
            $first = $locations->keys()->first();
            $defaultLocationId = $first ?? null;
        } elseif (is_array($permitted_locations) && count($permitted_locations) >= 1) {
            $defaultLocationId = $permitted_locations[0];
        }

        $paymentTypes = app(TransactionUtil::class)->payment_types(null, true, (int) ($business_id));

        $defaultPaymentMethod = array_key_exists('cash', $paymentTypes) ? 'cash' : (array_key_first($paymentTypes) ?? '');

        $collectors = DB::table('users')
            ->selectRaw("id, TRIM(CONCAT(COALESCE(first_name,''), ' ', COALESCE(last_name,''))) as name")
            ->orderBy('first_name')
            ->get();

        $defaultCollectorId = auth()->id();

        $loanLocations = collect();
        if (Schema::connection('mysql_loan')->hasTable('loan_business_locations')) {
            $loanLocations = DB::connection('mysql_loan')->table('loan_business_locations')
                ->whereNull('deleted_at')
                ->orderBy('name')
                ->get();
        }

        $recentLoans = $this->recentLoans();

        return view('loanmanagement::loans.standalone.create', compact(
            'locations',
            'paymentTypes',
            'defaultPaymentMethod',
            'collectors',
            'loanLocations',
            'defaultLocationId',
            'defaultCollectorId',
            'recentLoans'
        ));
    }

    public function modal()
    {
        $business_id = session('user.business_id');

        $locations = DB::table('business_locations')->orderBy('name')->pluck('name', 'id');

        $permitted_locations = auth()->user()->permitted_locations($business_id);
        $defaultLocationId = null;
        if ($permitted_locations === 'all') {
            $first = $locations->keys()->first();
            $defaultLocationId = $first ?? null;
        } elseif (is_array($permitted_locations) && count($permitted_locations) === 1) {
            $defaultLocationId = $permitted_locations[0];
        } elseif (is_array($permitted_locations) && count($permitted_locations) > 1) {
            $defaultLocationId = $permitted_locations[0];
        }

        $paymentTypes = app(TransactionUtil::class)->payment_types(null, true, (int) ($business_id));

        $defaultPaymentMethod = array_key_exists('cash', $paymentTypes) ? 'cash' : (array_key_first($paymentTypes) ?? '');

        $collectors = DB::table('users')
            ->selectRaw("id, TRIM(CONCAT(COALESCE(first_name,''), ' ', COALESCE(last_name,''))) as name")
            ->orderBy('first_name')
            ->get();

        $defaultCollectorId = auth()->id();

        $loanLocations = collect();
        if (Schema::connection('mysql_loan')->hasTable('loan_business_locations')) {
            $loanLocations = DB::connection('mysql_loan')->table('loan_business_locations')
                ->whereNull('deleted_at')
                ->orderBy('name')
                ->get();
        }

        $business_id = session('user.business_id');

        $types = [];
        if (auth()->user()->can('supplier.create') || auth()->user()->can('supplier.view_own')) {
            $types['supplier'] = __('report.supplier');
        }
        if (auth()->user()->can('customer.create') || auth()->user()->can('customer.view_own')) {
            $types['customer'] = __('report.customer');
        }
        if ((auth()->user()->can('supplier.create') && auth()->user()->can('customer.create')) || auth()->user()->can('supplier.view_own') || auth()->user()->can('customer.view_own')) {
            $types['both'] = __('lang_v1.both_supplier_customer');
        }

        $customer_groups = CustomerGroup::forDropdown($business_id);
        $selected_type = 'customer';
        $module_form_parts = app(ModuleUtil::class)->getModuleData('contact_form_part');
        $users = config('constants.enable_contact_assign') ? User::forDropdown($business_id, false, false, false, true) : [];

        return view('loanmanagement::loans.standalone.modal', compact(
            'locations',
            'paymentTypes',
            'defaultPaymentMethod',
            'collectors',
            'loanLocations',
            'types',
            'customer_groups',
            'selected_type',
            'module_form_parts',
            'users',
            'defaultLocationId',
            'defaultCollectorId'
        ));
    }

    protected function recentLoans(int $limit = 8)
    {
        if (! Schema::connection('mysql_loan')->hasTable('loans')) {
            return collect();
        }

        $columns = Schema::connection('mysql_loan')->getColumnListing('loans');
        $select = ['id'];

        foreach ([
            'loan_number',
            'loan_date',
            'customer_name_snapshot',
            'customer_phone_snapshot',
            'principal_amount',
            'paid_amount',
            'balance_amount',
            'status',
            'currency',
            'created_at',
        ] as $column) {
            if (in_array($column, $columns, true)) {
                $select[] = $column;
            }
        }

        $query = DB::connection('mysql_loan')->table('loans')->select($select);

        if (in_array('deleted_at', $columns, true)) {
            $query->whereNull('deleted_at');
        }

        if (in_array('created_at', $columns, true)) {
            $query->orderByDesc('created_at');
        } elseif (in_array('loan_date', $columns, true)) {
            $query->orderByDesc('loan_date');
        } else {
            $query->orderByDesc('id');
        }

        return $query->limit($limit)->get();
    }

    public function searchCustomers(Request $request): JsonResponse
    {
        $keyword = trim((string) $request->input('q', ''));

        if (mb_strlen($keyword) < 2) {
            return response()->json(['success' => true, 'data' => []]);
        }

        $customers = $this->service->searchCustomers($keyword);

        return response()->json([
            'success' => true,
            'data' => $customers,
        ]);
    }

    public function scanIdCard(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'id_card_image' => 'required|string',
        ]);

        try {
            $image = $this->decodeDataUriImage((string) $payload['id_card_image']);
            if ($image === null) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid ID card image.',
                    'data' => [],
                ], 422);
            }

            $tesseract = $this->findTesseractBinary();
            if ($tesseract === null) {
                return response()->json([
                    'success' => false,
                    'message' => 'OCR is not installed on this server.',
                    'data' => [
                        'raw_text' => '',
                        'fields' => [],
                    ],
                ]);
            }

            $tmpDir = $this->makeOcrTempDir();

            $imagePath = $tmpDir.'/'.uniqid('id-card-', true).'.jpg';
            File::put($imagePath, $image);

            $tessdataDir = $this->resolveTessdataDir($tesseract);
            $langs = $this->resolveTesseractLanguages($tessdataDir);
            $rawText = $this->runTesseract($tesseract, $imagePath, $tessdataDir, $langs, 6);
            if (str_contains($langs, 'khm')) {
                $khmerText = $this->runTesseract($tesseract, $imagePath, $tessdataDir, 'khm', 6);
                $khmerSparseText = $this->runTesseract($tesseract, $imagePath, $tessdataDir, 'khm', 11);
                $khmerNameCrop = $this->makeOcrCrop($image, $tmpDir, 'khmer-name', 0.22, 0.02, 0.58, 0.17);
                $khmerAddressCrop = $this->makeOcrCrop($image, $tmpDir, 'khmer-address', 0.22, 0.20, 0.74, 0.24);
                $khmerCropText = $khmerNameCrop ? $this->runTesseract($tesseract, $khmerNameCrop, $tessdataDir, 'khm', 7) : '';
                $khmerCropSparseText = $khmerNameCrop ? $this->runTesseract($tesseract, $khmerNameCrop, $tessdataDir, 'khm', 13) : '';
                $khmerAddressText = $khmerAddressCrop ? $this->runTesseract($tesseract, $khmerAddressCrop, $tessdataDir, 'khm', 6) : '';
                if ($khmerNameCrop) {
                    @unlink($khmerNameCrop);
                }
                if ($khmerAddressCrop) {
                    @unlink($khmerAddressCrop);
                }
                $rawText = trim($khmerCropText."\n".$khmerCropSparseText."\n".$rawText."\n".$khmerText."\n".$khmerSparseText."\n".$khmerAddressText);
            }
            @unlink($imagePath);

            $fields = $this->parseCambodianIdCardText($rawText);

            return response()->json([
                'success' => true,
                'message' => 'ID card text extracted.',
                'data' => [
                    'raw_text' => $rawText,
                    'fields' => $fields,
                    'languages' => $langs,
                    'tessdata_dir' => $tessdataDir,
                ],
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'data' => [],
            ], 422);
        }
    }

    public function scanProductPhoto(
        Request $request,
        GoogleVisionOcrService $ocrService,
        OpenAiProductPhotoParserService $parserService
    ): JsonResponse
    {
        $payload = $request->validate([
            'product_image' => 'required|string',
        ]);

        $imagePath = null;

        try {
            $image = $this->decodeDataUriImage((string) $payload['product_image']);
            if ($image === null) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid product photo.',
                    'data' => [],
                ], 422);
            }

            if (strlen($image) > 10 * 1024 * 1024) {
                return response()->json([
                    'success' => false,
                    'message' => 'Product photo must not be larger than 10MB.',
                    'data' => [],
                ], 422);
            }

            $tmpDir = $this->makeOcrTempDir();
            $imagePath = $tmpDir.'/'.uniqid('product-photo-', true).'.jpg';
            File::put($imagePath, $image);

            $rawText = '';
            try {
                $rawText = $ocrService->extractText($imagePath);
                $fields = $this->parseProductText($parserService, $rawText);
            } catch (\RuntimeException $exception) {
                if (! str_contains($exception->getMessage(), 'Google Cloud Vision API key is not configured')) {
                    throw $exception;
                }

                $rawText = $this->extractProductTextWithLocalOcr($imagePath);
                if ($rawText !== '') {
                    $fields = $this->parseProductText($parserService, $rawText);
                } else {
                    try {
                        $fields = $parserService->parseImage((string) $payload['product_image']);
                    } catch (\RuntimeException $fallbackException) {
                        if (! str_contains($fallbackException->getMessage(), 'OpenAI API key is not configured')) {
                            throw $fallbackException;
                        }

                        return response()->json([
                            'success' => true,
                            'message' => 'Product photo saved. OCR provider is not configured.',
                            'data' => [
                                'raw_text' => '',
                                'fields' => [],
                            ],
                        ]);
                    }
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Product photo text extracted.',
                'data' => [
                    'raw_text' => $rawText,
                    'fields' => $fields,
                ],
            ]);
        } catch (\Throwable $exception) {
            Log::error('Loan product photo OCR failed.', [
                'exception' => get_class($exception),
                'message' => $exception->getMessage(),
            ]);

            $message = str_replace('ID card', 'product photo', $exception->getMessage());

            return response()->json([
                'success' => false,
                'message' => $message,
                'data' => [],
            ], 422);
        } finally {
            if ($imagePath !== null && File::exists($imagePath)) {
                File::delete($imagePath);
            }
        }
    }

    protected function parseProductText(OpenAiProductPhotoParserService $parserService, string $rawText): array
    {
        try {
            return $parserService->parse($rawText);
        } catch (\RuntimeException $exception) {
            if (! str_contains($exception->getMessage(), 'OpenAI API key is not configured')) {
                throw $exception;
            }

            return $this->parseProductTextLocally($rawText);
        }
    }

    protected function parseProductTextLocally(string $rawText): array
    {
        $text = trim(str_replace(["\r", "\t"], ["\n", ' '], $rawText));
        $lines = collect(preg_split('/\n+/', $text) ?: [])
            ->map(fn ($line) => trim(preg_replace('/\s+/', ' ', (string) $line)))
            ->filter()
            ->values()
            ->all();

        $fields = [
            'product_name' => null,
            'color' => null,
            'storage' => null,
            'serial_number' => null,
            'imei' => null,
        ];

        if (preg_match('/\b(?:IMEI|IMEI1|IMEI 1|IMEI2|IMEI 2)\s*[:#-]?\s*([0-9\s-]{14,20})\b/i', $text, $match)
            || preg_match('/\b([0-9]{15})\b/', $text, $match)) {
            $fields['imei'] = preg_replace('/\D+/', '', $match[1]);
        }

        if (preg_match('/\b(?:S\/N|SN|Serial(?:\s+No\.?|\s+Number)?|Ser(?:ial)?)\s*[:#-]?\s*([A-Z0-9-]{5,40})\b/i', $text, $match)) {
            $fields['serial_number'] = strtoupper(trim($match[1]));
        }

        if (preg_match('/\b((?:\d+\s*(?:GB|TB))(?:\s*\/\s*\d+\s*(?:GB|TB))?)\b/i', $text, $match)) {
            $fields['storage'] = strtoupper(preg_replace('/\s+/', '', $match[1]));
        }

        $colors = [
            'black', 'white', 'silver', 'gold', 'blue', 'red', 'green', 'purple', 'pink',
            'yellow', 'gray', 'grey', 'graphite', 'midnight', 'starlight', 'natural titanium',
            'titanium', 'rose gold', 'space gray', 'space grey',
        ];
        foreach ($colors as $color) {
            if (preg_match('/\b'.preg_quote($color, '/').'\b/i', $text)) {
                $fields['color'] = ucwords($color);
                break;
            }
        }

        foreach ($lines as $line) {
            if (preg_match('/IMEI|Serial|S\/N|SN|Color|Colour|Storage|Capacity|Memory|Model No|Barcode|Warranty|Price|Qty/i', $line)) {
                continue;
            }
            if (preg_match('/^[A-Z0-9][A-Z0-9\s+().\/-]{3,80}$/i', $line) && ! preg_match('/^\d+$/', $line)) {
                $fields['product_name'] = trim($line);
                break;
            }
        }

        return array_filter($fields, fn ($value) => $value !== null && $value !== '');
    }

    protected function extractProductTextWithLocalOcr(string $imagePath): string
    {
        $tesseract = $this->findTesseractBinary();
        if ($tesseract === null) {
            return '';
        }

        $tessdataDir = $this->resolveTessdataDir($tesseract);
        $text = trim($this->runTesseract($tesseract, $imagePath, $tessdataDir, 'eng', 6));

        if ($text === '') {
            $text = trim($this->runTesseract($tesseract, $imagePath, $tessdataDir, 'eng', 11));
        }

        return $text;
    }

    protected function runTesseract(string $tesseract, string $imagePath, ?string $tessdataDir, string $langs, int $psm): string
    {
        $cmd = escapeshellarg($tesseract).' '.escapeshellarg($imagePath).' stdout';
        if ($tessdataDir !== null) {
            $cmd .= ' --tessdata-dir '.escapeshellarg($tessdataDir);
        }
        $cmd .= ' -l '.escapeshellarg($langs).' --psm '.((int) $psm).' -c preserve_interword_spaces=1 2>&1';

        return (string) shell_exec($cmd);
    }

    protected function makeOcrTempDir(): string
    {
        $dirs = [
            storage_path('app/loan-id-card-ocr'),
            base_path('Modules/LoanManagement/storage/ocr-temp'),
            sys_get_temp_dir().DIRECTORY_SEPARATOR.'loan-id-card-ocr',
        ];

        foreach ($dirs as $dir) {
            try {
                if (! File::isDirectory($dir)) {
                    File::makeDirectory($dir, 0755, true);
                }
                if (is_writable($dir)) {
                    return $dir;
                }
            } catch (\Throwable $e) {
            }
        }

        return sys_get_temp_dir();
    }

    protected function makeOcrCrop(string $imageBinary, string $tmpDir, string $prefix, float $x, float $y, float $w, float $h): ?string
    {
        if (! function_exists('imagecreatefromstring')) {
            return null;
        }

        $source = @imagecreatefromstring($imageBinary);
        if (! $source) {
            return null;
        }

        $sourceW = imagesx($source);
        $sourceH = imagesy($source);
        $cropX = max(0, (int) floor($sourceW * $x));
        $cropY = max(0, (int) floor($sourceH * $y));
        $cropW = min($sourceW - $cropX, (int) ceil($sourceW * $w));
        $cropH = min($sourceH - $cropY, (int) ceil($sourceH * $h));
        if ($cropW <= 0 || $cropH <= 0) {
            imagedestroy($source);
            return null;
        }

        $scale = 3;
        $target = imagecreatetruecolor($cropW * $scale, $cropH * $scale);
        imagecopyresampled($target, $source, 0, 0, $cropX, $cropY, $cropW * $scale, $cropH * $scale, $cropW, $cropH);
        imagefilter($target, IMG_FILTER_GRAYSCALE);
        imagefilter($target, IMG_FILTER_CONTRAST, -25);

        $path = $tmpDir.'/'.uniqid($prefix.'-', true).'.png';
        imagepng($target, $path);
        imagedestroy($target);
        imagedestroy($source);

        return $path;
    }

    public function previewSchedule(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'principal_amount' => 'required|numeric|min:0.01',
            'interest_rate' => 'nullable|numeric|min:0',
            'interest_type' => 'required|in:flat,reducing_balance',
            'duration_months' => 'required|integer|min:1|max:360',
            'payment_frequency' => 'required|in:monthly,weekly,daily',
            'first_due_date' => 'required|date',
            'items' => 'nullable|array',
            'items.*.product_name' => 'nullable|string|max:255',
            'items.*.qty' => 'nullable|integer|min:1',
            'items.*.unit_price' => 'nullable|numeric|min:0',
            'down_payment' => 'nullable|numeric|min:0',
            'payments' => 'nullable|array',
            'payments.*.amount' => 'nullable|numeric|min:0',
        ]);

        $rows = $this->service->previewSchedule($payload);

        return response()->json([
            'success' => true,
            'message' => 'Schedule preview generated',
            'data' => $rows,
        ]);
    }

    protected function decodeDataUriImage(string $dataUri): ?string
    {
        if (preg_match('/^data:image\/[a-zA-Z0-9.+-]+;base64,/', $dataUri)) {
            $dataUri = substr($dataUri, strpos($dataUri, ',') + 1);
        }

        $decoded = base64_decode($dataUri, true);
        return $decoded === false ? null : $decoded;
    }

    protected function findTesseractBinary(): ?string
    {
        $configured = trim((string) (config('loanmanagement.tesseract.path') ?? env('TESSERACT_PATH', '')));
        $candidates = array_filter([
            $configured,
            trim((string) shell_exec('command -v tesseract 2>/dev/null')),
            'C:\\Program Files\\Tesseract-OCR\\tesseract.exe',
            'C:\\Program Files (x86)\\Tesseract-OCR\\tesseract.exe',
            '/usr/bin/tesseract',
            '/usr/local/bin/tesseract',
        ]);

        foreach ($candidates as $candidate) {
            if (@is_file($candidate) && @is_executable($candidate)) {
                return $candidate;
            }
            if (@is_file($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    protected function resolveTessdataDir(string $tesseract): ?string
    {
        $tessdataDirs = [
            base_path('Modules/LoanManagement/storage/tesseract/tessdata'),
            dirname($tesseract).DIRECTORY_SEPARATOR.'tessdata',
            '/usr/share/tesseract-ocr/5/tessdata',
            '/usr/share/tesseract-ocr/4.00/tessdata',
            '/usr/share/tessdata',
        ];

        foreach ($tessdataDirs as $dir) {
            if (@is_dir($dir) && (@is_file($dir.DIRECTORY_SEPARATOR.'eng.traineddata') || @is_file($dir.DIRECTORY_SEPARATOR.'khm.traineddata'))) {
                return $dir;
            }
        }

        return null;
    }

    protected function resolveTesseractLanguages(?string $tessdataDir): string
    {
        if ($tessdataDir === null) {
            return 'eng';
        }

        $hasKhmer = false;
        $hasEnglish = false;
        if (@is_file($tessdataDir.DIRECTORY_SEPARATOR.'khm.traineddata')) {
            $hasKhmer = true;
        }
        if (@is_file($tessdataDir.DIRECTORY_SEPARATOR.'eng.traineddata')) {
            $hasEnglish = true;
        }

        if ($hasKhmer && $hasEnglish) {
            return 'khm+eng';
        }

        return $hasEnglish ? 'eng' : 'khm+eng';
    }

    protected function parseCambodianIdCardText(string $rawText): array
    {
        $text = trim(str_replace(["\r", "\t"], ["\n", ' '], $rawText));
        $lines = collect(preg_split('/\n+/', $text) ?: [])
            ->map(fn ($line) => trim(preg_replace('/\s+/', ' ', (string) $line)))
            ->filter()
            ->values()
            ->all();

        $fields = [
            'id_card_number' => null,
            'khmer_name' => null,
            'english_name' => null,
            'address' => null,
        ];

        if (preg_match('/\b\d[\d\s-]{6,}\d\b/u', $text, $match)) {
            $fields['id_card_number'] = preg_replace('/\D+/', '', $match[0]);
        }

        foreach ($lines as $line) {
            $mrzLine = strtoupper(preg_replace('/\s+/', '', $line));
            if (preg_match('/^IDKHM(\d{8,10})\d*<+/i', $mrzLine, $match)) {
                $fields['id_card_number'] = $match[1];
                break;
            }
        }

        foreach ($lines as $index => $line) {
            $lower = mb_strtolower($line);
            if ($fields['english_name'] === null && preg_match('/(?:name|surname|given name)[:\s]+([A-Z][A-Z\s.\'-]{2,})/i', $line, $match)) {
                $fields['english_name'] = trim($match[1]);
            }
            if ($fields['khmer_name'] === null && preg_match('/គោត្តនាម|គោគ្គនាម|នាមនិ|ឈ្មោះ/u', $line)) {
                $khmerName = $this->extractKhmerNameFromLabelLine($line);
                if ($khmerName === '' && isset($lines[$index + 1])) {
                    $khmerName = $this->cleanKhmerNameValue($lines[$index + 1]);
                }
                if ($khmerName !== '') {
                    $fields['khmer_name'] = $khmerName;
                }
            }
            if ($fields['khmer_name'] === null && preg_match('/[\x{1780}-\x{17FF}]/u', $line) && ! preg_match('/អាសយដ្ឋាន|ទីលំនៅ|ភូមិ|ឃុំ|សង្កាត់|ស្រុក|ខណ្ឌ|ខេត្ត|ក្រុង|ថ្ងៃខែឆ្នាំ|ភេទ|កម្ពស់/u', $line)) {
                $khmerName = $this->cleanKhmerNameValue($this->cleanKhmerLabel($line));
                if ($khmerName !== '') {
                    $fields['khmer_name'] = $khmerName;
                }
            }
            if ($fields['address'] === null && (str_contains($lower, 'address') || preg_match('/អាសយដ្ឋាន|ទីលំនៅ|ទីកន្លែងកំណើត/u', $line))) {
                $addressParts = [$this->cleanAddressLabel($line)];
                for ($i = $index + 1; $i < min(count($lines), $index + 4); $i++) {
                    if (preg_match('/date|dob|sex|height|expiry|សុពលភាព|ថ្ងៃខែឆ្នាំ|ភេទ/i', $lines[$i])) {
                        break;
                    }
                    $addressParts[] = $lines[$i];
                }
                $fields['address'] = trim(implode(' ', array_filter($addressParts)));
            }
        }

        if ($fields['khmer_name'] === null) {
            $fields['khmer_name'] = $this->guessBestKhmerName($lines);
        }

        if ($fields['english_name'] === null) {
            foreach ($lines as $line) {
                $mrzNameLine = strtoupper(preg_replace('/\s+/', '', $line));
                if (preg_match('/^([A-Z]+)<+([A-Z]+)(?:<+)?$/', $mrzNameLine, $match)) {
                    $fields['english_name'] = trim($match[1].' '.$match[2]);
                    break;
                }
            }
        }

        if ($fields['english_name'] === null) {
            foreach ($lines as $line) {
                if (preg_match('/^[A-Z][A-Z\s.\'-]{4,}$/', $line) && ! preg_match('/KINGDOM|CAMBODIA|IDENTITY|CARD|NATION/i', $line)) {
                    $fields['english_name'] = trim($line);
                    break;
                }
            }
        }

        return array_filter($fields, fn ($value) => $value !== null && $value !== '');
    }

    protected function cleanKhmerLabel(string $value): string
    {
        $value = preg_replace('/^(គោត្តនាមនិងនាម|គោគ្គនាមនិឯនាម|គោត្តនាម.*?នាម|គោគ្គនាម.*?នាម|នាម|ឈ្មោះ|Name)\s*[:：-]?\s*/u', '', $value);
        return trim((string) $value);
    }

    protected function extractKhmerNameFromLabelLine(string $line): string
    {
        if (preg_match('/(?:គោត្តនាមនិងនាម|គោគ្គនាមនិឯនាម|គោត្តនាម.*?នាម|គោគ្គនាម.*?នាម|ឈ្មោះ)\s*[:：-]?\s*(.+)$/u', $line, $match)) {
            return $this->cleanKhmerNameValue($match[1]);
        }

        return $this->cleanKhmerNameValue($this->cleanKhmerLabel($line));
    }

    protected function cleanKhmerNameValue(string $value): string
    {
        $value = preg_replace('/[^\x{1780}-\x{17FF}\s]+/u', ' ', $value);
        $value = trim(preg_replace('/\s+/u', ' ', (string) $value));

        if ($value === '' || preg_match('/អាសយដ្ឋាន|ទីលំនៅ|ទីកន្លែងកំណើត|ថ្ងៃខែឆ្នាំ|ភេទ|កម្ពស់/u', $value)) {
            return '';
        }

        return $value;
    }

    protected function guessBestKhmerName(array $lines): ?string
    {
        $best = null;
        $bestScore = 0;

        foreach ($lines as $line) {
            $candidate = $this->cleanKhmerNameValue($this->cleanKhmerLabel($line));
            if ($candidate === '') {
                continue;
            }

            $words = preg_split('/\s+/u', $candidate, -1, PREG_SPLIT_NO_EMPTY) ?: [];
            $khmerChars = preg_match_all('/[\x{1780}-\x{17FF}]/u', $candidate);
            $score = $khmerChars + (count($words) >= 2 ? 20 : 0) - (count($words) > 4 ? 10 : 0);

            if (preg_match('/គោត្តនាម|គោគ្គនាម|នាមនិ|ឈ្មោះ|អត្តសញ្ញាណ/u', $line)) {
                $score -= 30;
            }

            if ($score > $bestScore) {
                $best = $candidate;
                $bestScore = $score;
            }
        }

        return $bestScore > 0 ? $best : null;
    }

    protected function cleanAddressLabel(string $value): string
    {
        return trim(preg_replace('/^(Address|អាសយដ្ឋាន|ទីលំនៅ|ទីកន្លែងកំណើត)\s*[:：-]?\s*/iu', '', $value));
    }

    public function store(StoreStandaloneLoanRequest $request): JsonResponse
    {
        try {
            $validated = $request->validated();
            $loanId = $this->service->createStandaloneLoan($validated);

            return response()->json([
                'success' => true,
                'message' => 'Loan created successfully',
                'data' => ['loan_id' => $loanId],
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'data' => [],
            ], 422);
        }
    }

    public function calculator()
    {
        $defaults = [
            'total_price' => 0,
            'down_payment' => 0,
            'interest_rate' => 4,
            'interest_type' => 'flat',
            'duration_months' => 12,
            'first_due_date' => Carbon::today()->addMonth()->toDateString(),
            'currency' => session('currency.code', 'USD'),
        ];

        if (! empty(request('_lm_modal'))) {
            return view('loanmanagement::loans.calculator', compact('defaults'));
        }

        return view('loanmanagement::loans.calculator', compact('defaults'));
    }

    public function calculatorPrint(Request $request)
    {
        $data = $request->validate([
            'total_price' => 'nullable|numeric|min:0',
            'down_payment' => 'nullable|numeric|min:0',
            'interest_rate' => 'nullable|numeric|min:0',
            'interest_type' => 'nullable|in:flat,reducing_balance',
            'duration_months' => 'nullable|integer|min:1|max:360',
            'first_due_date' => 'nullable|date',
        ]);

        $totalPrice = round((float) ($data['total_price'] ?? 0), 2);
        $downPayment = min($totalPrice, round((float) ($data['down_payment'] ?? 0), 2));
        $principal = max(0, round($totalPrice - $downPayment, 2));
        $durationMonths = max(1, (int) ($data['duration_months'] ?? 12));
        $interestRate = (float) ($data['interest_rate'] ?? 4);
        $interestType = $data['interest_type'] ?? 'flat';
        $firstDueDate = $data['first_due_date'] ?? Carbon::today()->addMonth()->toDateString();

        $scheduleRows = $principal > 0
            ? $this->service->previewSchedule([
                'principal_amount' => $principal,
                'interest_rate' => $interestRate,
                'interest_type' => $interestType,
                'duration_months' => $durationMonths,
                'payment_frequency' => 'monthly',
                'first_due_date' => $firstDueDate,
            ])
            : collect(range(1, $durationMonths))->map(function ($month) use ($firstDueDate) {
                return [
                    'schedule_no' => $month,
                    'due_date' => Carbon::parse($firstDueDate)->addMonths($month - 1)->toDateString(),
                    'principal' => 0,
                    'interest' => 0,
                    'total' => 0,
                    'balance' => 0,
                ];
            })->all();

        $installments = collect($scheduleRows)->map(function ($row) {
            return (object) [
                'installment_number' => $row['schedule_no'],
                'installmentdate' => $row['due_date'],
                'installment_value' => $row['principal'],
                'benefit_value' => $row['interest'],
                'amount_due' => $row['total'],
                'balance' => $row['balance'],
            ];
        });

        $businessName = session('business.name', 'Loan Management');
        $printedAt = Carbon::now()->format('d-M-Y H:i:s');
        $loanDate = Carbon::today()->format('m-d-Y');
        $loanDateTitle = Carbon::today()->format('d-M-Y');
        $lastDueDate = optional($installments->last())->installmentdate;
        $schedulePrincipalTotal = round($installments->sum('installment_value'), 2);
        $scheduleInterestTotal = round($installments->sum('benefit_value'), 2);
        $scheduleTotalAmount = round($installments->sum('amount_due'), 2);
        $downPercent = $totalPrice > 0 ? round($downPayment / $totalPrice * 100, 2) : 0;

        return view('loanmanagement::loans.print.calculator', compact(
            'businessName',
            'totalPrice',
            'downPayment',
            'principal',
            'durationMonths',
            'interestRate',
            'interestType',
            'firstDueDate',
            'lastDueDate',
            'installments',
            'printedAt',
            'loanDate',
            'loanDateTitle',
            'schedulePrincipalTotal',
            'scheduleInterestTotal',
            'scheduleTotalAmount',
            'downPercent'
        ));
    }
}
