<?php

namespace Modules\LoanManagement\Services;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class OpenAiProductPhotoParserService
{
    private const FIELDS = [
        'product_name',
        'color',
        'storage',
        'serial_number',
        'imei',
    ];

    public function parse(string $ocrText): array
    {
        return $this->send($this->payloadFromText($ocrText));
    }

    public function parseImage(string $imageDataUri): array
    {
        return $this->send($this->payloadFromImage($imageDataUri));
    }

    private function send(array $payload): array
    {
        $apiKey = $this->apiKey();

        if (empty($apiKey)) {
            throw new RuntimeException('OpenAI API key is not configured.');
        }

        $headers = [
            'Content-Type' => 'application/json',
        ];

        if (! empty(config('loanmanagement.openai.organization'))) {
            $headers['OpenAI-Organization'] = config('loanmanagement.openai.organization');
        }

        try {
            $response = Http::withToken($apiKey)
                ->withHeaders($headers)
                ->timeout(config('loanmanagement.openai.timeout', 45))
                ->retry(2, 300)
                ->post('https://api.openai.com/v1/responses', $payload);
        } catch (\Throwable $exception) {
            Log::error('OpenAI product photo parsing request failed.', [
                'exception' => get_class($exception),
                'message' => $exception->getMessage(),
            ]);

            throw new RuntimeException('Unable to understand the product photo text. Please try again.');
        }

        if (! $response->successful()) {
            Log::error('OpenAI product photo parsing returned an error.', [
                'status' => $response->status(),
            ]);

            throw new RuntimeException('Unable to understand the product photo text. Please try again.');
        }

        $decoded = json_decode($this->extractJson($response->json()), true);

        if (! is_array($decoded)) {
            throw new RuntimeException('Unable to understand the product photo text. Please try again.');
        }

        return $this->normalize($decoded);
    }

    private function payloadFromText(string $ocrText): array
    {
        return [
            'model' => config('loanmanagement.openai.product_photo_model', config('loanmanagement.openai.id_card_model', 'gpt-4.1-mini')),
            'input' => [
                [
                    'role' => 'developer',
                    'content' => [
                        [
                            'type' => 'input_text',
                            'text' => $this->instructions(),
                        ],
                    ],
                ],
                [
                    'role' => 'user',
                    'content' => [
                        [
                            'type' => 'input_text',
                            'text' => "OCR text from one product label, box, invoice sticker, or device settings screen:\n\n".$ocrText,
                        ],
                    ],
                ],
            ],
            'text' => [
                'format' => [
                    'type' => 'json_schema',
                    'name' => 'loan_product_photo',
                    'strict' => true,
                    'schema' => $this->schema(),
                ],
            ],
        ];
    }

    private function payloadFromImage(string $imageDataUri): array
    {
        return [
            'model' => config('loanmanagement.openai.product_photo_model', config('loanmanagement.openai.id_card_model', 'gpt-4.1-mini')),
            'input' => [
                [
                    'role' => 'developer',
                    'content' => [
                        [
                            'type' => 'input_text',
                            'text' => $this->instructions(),
                        ],
                    ],
                ],
                [
                    'role' => 'user',
                    'content' => [
                        [
                            'type' => 'input_text',
                            'text' => 'Extract product information from this product photo.',
                        ],
                        [
                            'type' => 'input_image',
                            'image_url' => $imageDataUri,
                        ],
                    ],
                ],
            ],
            'text' => [
                'format' => [
                    'type' => 'json_schema',
                    'name' => 'loan_product_photo',
                    'strict' => true,
                    'schema' => $this->schema(),
                ],
            ],
        ];
    }

    private function instructions(): string
    {
        return implode("\n", [
            'Extract product information for a loan item from OCR text.',
            'Return only information supported by the OCR text. Use null for missing fields.',
            'Product name should be a concise product/model name, for example Phone 13 Pro Max or Samsung Galaxy A15.',
            'For Apple phones, write Phone instead of iPhone.',
            'Color should be the detected product color only.',
            'Storage should include capacity and units, for example 128GB or 8GB/256GB.',
            'Serial number should use labels such as Serial, S/N, SN, Serial No, or barcode text clearly used as a serial.',
            'IMEI should use digits only and prefer 15-digit IMEI values when present.',
            'Do not copy phone numbers, prices, addresses, dates, or unrelated invoice numbers into serial_number or imei.',
        ]);
    }

    private function schema(): array
    {
        $properties = [];

        foreach (self::FIELDS as $field) {
            $properties[$field] = [
                'type' => ['string', 'null'],
            ];
        }

        return [
            'type' => 'object',
            'additionalProperties' => false,
            'properties' => $properties,
            'required' => self::FIELDS,
        ];
    }

    private function extractJson(array $payload): string
    {
        if (! empty($payload['output_text'])) {
            return $payload['output_text'];
        }

        foreach (Arr::wrap($payload['output'] ?? []) as $output) {
            foreach (Arr::wrap($output['content'] ?? []) as $content) {
                if (! empty($content['text'])) {
                    return $content['text'];
                }
            }
        }

        throw new RuntimeException('Unable to understand the product photo text. Please try again.');
    }

    private function normalize(array $data): array
    {
        $normalized = [];

        foreach (self::FIELDS as $field) {
            $value = $data[$field] ?? null;
            $normalized[$field] = is_string($value) && trim($value) !== '' ? trim($value) : null;
        }

        if (! empty($normalized['product_name'])) {
            $normalized['product_name'] = trim(preg_replace('/^(?:model\s*name|product\s*name|model|product|item\s*name|device\s*name)\s*[:：-]?\s*/iu', '', $normalized['product_name']));
            $normalized['product_name'] = preg_replace('/\biPhone\b/i', 'Phone', $normalized['product_name']);
        }

        if (! empty($normalized['imei'])) {
            $normalized['imei'] = preg_replace('/\D+/', '', $normalized['imei']);
        }

        return $normalized;
    }

    private function apiKey(): ?string
    {
        $candidates = [
            config('loanmanagement.openai.api_key'),
            env('OPENAI_API_KEY'),
            $this->envFileValue('OPENAI_API_KEY'),
        ];

        foreach ($candidates as $candidate) {
            $candidate = trim((string) $candidate);
            if ($candidate !== '') {
                return $candidate;
            }
        }

        return null;
    }

    private function envFileValue(string $key): ?string
    {
        $path = base_path('.env');

        if (! is_readable($path)) {
            return null;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (! is_array($lines)) {
            return null;
        }

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#') || ! str_contains($line, '=')) {
                continue;
            }

            [$name, $value] = explode('=', $line, 2);
            if (trim($name) !== $key) {
                continue;
            }

            return trim(trim($value), "\"'");
        }

        return null;
    }
}
