<?php

namespace Modules\ClearDataByDate\Exceptions;

use Exception;

class PurchaseSellMismatch extends Exception
{
    public function __construct($message)
    {
        parent::__construct($message);
    }

    public function render($request)
    {
        $output = ['success' => 0,
            'msg' => $this->getMessage(),
        ];

        if ($request->ajax()) {
            return $output;
        }

        throw new Exception($this->getMessage());
    }
}

