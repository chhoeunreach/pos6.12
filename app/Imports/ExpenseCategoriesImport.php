<?php

namespace App\Imports;

use Maatwebsite\Excel\Concerns\ToArray;

class ExpenseCategoriesImport implements ToArray
{
    // Read the original headings so Khmer text is not altered by heading slugging.
    public function array(array $array)
    {
        return $array;
    }
}
