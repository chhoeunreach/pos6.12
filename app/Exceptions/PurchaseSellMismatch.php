<?php

namespace App\Exceptions;

use Exception;

class PurchaseSellMismatch extends Exception
{
    /**
     * Create a new authentication exception.
     *
     * @param  string  $message
     * @param  array  $guards
     * @return void
     */
    public function __construct($message, protected array $stockDetails = [])
    {
        parent::__construct($message);
    }

    public function responseData(): array
    {
        $output = ['success' => 0, 'msg' => $this->getMessage()];
        if ($this->stockDetails) {
            $output['stock_error'] = $this->stockDetails;
        }

        return $output;
    }

    /**
     * Render the exception as an HTTP response.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function render($request)
    {
        $output = $this->responseData();

        if ($request->ajax()) {
            return $output;
        } else {
            throw new Exception($this->getMessage());
        }
    }
}
