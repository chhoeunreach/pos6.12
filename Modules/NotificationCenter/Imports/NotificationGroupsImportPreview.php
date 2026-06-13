<?php

namespace Modules\NotificationCenter\Imports;

use Maatwebsite\Excel\Concerns\ToArray;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class NotificationGroupsImportPreview implements ToArray, WithHeadingRow
{
    public function array(array $array)
    {
        return $array;
    }
}
