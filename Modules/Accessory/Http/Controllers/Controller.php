<?php

namespace Modules\Accessory\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;

class Controller extends BaseController
{
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;

    protected $statusCode;

    public function getStatusCode()
    {
        return $this->statusCode;
    }

    public function setStatusCode($statusCode)
    {
        $this->statusCode = $statusCode;

        return $this;
    }

    public function respondWithError($message = null)
    {
        return response()->json(
            ['success' => false, 'msg' => $message]
        );
    }

    /**
     * Returns a Unauthorized response.
     *
     * @param  string  $message
     * @return \Illuminate\Http\Response
     */
    public function respondUnauthorized($message = 'Unauthorized action.')
    {
        return $this->setStatusCode(403)
            ->respondWithError($message);
    }

    /**
     * Returns a went wrong response.
     *
     * @param  object  $exception = null
     * @return \Illuminate\Http\Response
     */
    public function respondWentWrong($exception = null)
    {
        //If debug is enabled then send exception message
        $message = (config('app.debug') && is_object($exception)) ? 'File:'.$exception->getFile().'Line:'.$exception->getLine().'Message:'.$exception->getMessage() : __('messages.something_went_wrong');

        //TODO: show exception error message when error is enabled.
        return $this->setStatusCode(200)
            ->respondWithError($message);
    }

    /**
     * Returns a 200 response.
     *
     * @param  object  $message = null
     * @return \Illuminate\Http\Response
     */
    public function respondSuccess($message = null, $additional_data = [])
    {
        $message = is_null($message) ? __('lang_v.success') : $message;
        $data = ['success' => true, 'msg' => $message];

        if (! empty($additional_data)) {
            $data = array_merge($data, $additional_data);
        }

        return $this->respond($data);
    }

    /**
     * Returns a 200 response.
     *
     * @param  array  $data
     * @return \Illuminate\Http\Response
     */
    public function respond($data)
    {
        return response()->json($data);
    }

    protected function getAccessoryModuleData($moduleUtil, string $functionName, $arguments = null): array
    {
        $moduleNames = $this->getAccessoryAllowedModuleNames();

        return $moduleUtil->getModuleData($functionName, $arguments, $moduleNames);
    }

    protected function isAccessoryModuleInstalled($moduleUtil, string $moduleName): bool
    {
        if (in_array(strtolower($moduleName), $this->getAccessoryExcludedModuleNames(), true)) {
            return false;
        }

        return $moduleUtil->isModuleInstalled($moduleName);
    }

    private function getAccessoryAllowedModuleNames(): array
    {
        $excludedModules = $this->getAccessoryExcludedModuleNames();
        $modules = \Module::toCollection()->toArray();
        $moduleNames = [];

        foreach ($modules as $module) {
            $name = $module['name'] ?? null;
            if (! empty($name) && ! in_array(strtolower($name), $excludedModules, true)) {
                $moduleNames[] = $name;
            }
        }

        return $moduleNames;
    }

    private function getAccessoryExcludedModuleNames(): array
    {
        return array_map('strtolower', config('accessory.excluded_main_modules', []));
    }

    /**
     * Returns new mpdf instance
     * 
     * @param string $orientation 'P' for portrait (default), 'L' for landscape
     * @return \Mpdf\Mpdf
     */
    public function getMpdf($orientation = 'P')
    {
        $mpdf = new \Mpdf\Mpdf(['tempDir' => public_path('uploads/temp'),
            'mode' => 'utf-8',
            'autoScriptToLang' => true,
            'autoLangToFont' => true,
            'autoVietnamese' => true,
            'autoArabic' => true,
            'useSubstitutions' => true,
            'orientation' => $orientation,
        ]);

        if (auth()->check() && in_array(auth()->user()->language, config('constants.langs_rtl'))) {
            $mpdf->SetDirectionality('rtl');
        }

        return $mpdf;
    }
}

