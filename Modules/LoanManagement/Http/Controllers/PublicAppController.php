<?php

namespace Modules\LoanManagement\Http\Controllers;

use Illuminate\Routing\Controller;

class PublicAppController extends Controller
{
    use ApiResponseTrait;

    public function appSettings()
    {
        return $this->ok('App settings loaded', [
            'app_name' => 'LoanManagement',
            'support_chat' => true,
            'support_gps' => true,
            'support_aba_payway' => true,
            'support_file_upload' => true,
            'chat_polling_seconds' => (int) config('loanmanagement.chat.polling_interval_seconds', config('loanmanagement.chat_polling_seconds', 5)),
            'customer_api_guard' => (string) config('loanmanagement.customer_api_guard', 'customer_loan_api'),
        ]);
    }

    public function appVersion()
    {
        return $this->ok('App version loaded', [
            'module' => 'LoanManagement',
            'version' => (string) config('loanmanagement.version', '1.0.0'),
            'min_flutter_version' => '1.0.0',
        ]);
    }
}
