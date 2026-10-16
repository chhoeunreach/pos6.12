<?php

namespace App\Services;

use App\Exceptions\PurchaseSellMismatch;

/** Reads only transaction/receipt data from a SQL export; never executes its SQL. */
class AuthorityReceiptBackupReader
{
    public function read(string $path, int $transferId, int $receiptId): array
    {
        $stream = fopen($path, 'rb');
        if (!$stream) { throw new PurchaseSellMismatch('The backup could not be opened.'); }
        $columns = []; $transactions = []; $receipts = [];
        try {
            while (($line = fgets($stream)) !== false) {
                if (preg_match('/^CREATE TABLE(?: IF NOT EXISTS)? `(?<table>transactions|purchase_lines)`\s*\(/i', $line, $match)) {
                    $table = $match['table']; $columns[$table] = [];
                    while (($definition = fgets($stream)) !== false) {
                        if (preg_match('/^\s*\)/', $definition)) { break; }
                        if (preg_match('/^\s*`([^`]+)`\s+/', $definition, $field)) { $columns[$table][] = $field[1]; }
                    }
                }
                if (!preg_match('/^INSERT INTO `(?<table>transactions|purchase_lines)`\s*(?:\((?<columns>[^)]+)\))?\s*VALUES\s*/i', $line, $match)) { continue; }
                $table = $match['table'];
                $fields = !empty($match['columns'])
                    ? array_map(fn ($value) => trim($value, " `\t\r\n"), explode(',', $match['columns']))
                    : ($columns[$table] ?? []);
                if (count($fields) !== count(array_unique($fields))) { throw new PurchaseSellMismatch('The backup has duplicate column names.'); }
                if (!$fields) { throw new PurchaseSellMismatch('Backup column names are missing. Use a standard SQL export with table definitions.'); }
                $statement = substr($line, strlen($match[0]));
                if (strlen($statement) > 33554432) { throw new PurchaseSellMismatch('A backup INSERT statement exceeds 32 MB. Export smaller INSERT batches.'); }
                while (!preg_match('/;\s*$/D', $statement)) {
                    $next = fgets($stream);
                    if ($next === false || strlen($statement) + strlen($next) > 33554432) {
                        throw new PurchaseSellMismatch('The backup contains an incomplete or oversized INSERT statement.');
                    }
                    $statement .= $next;
                }
                foreach ($this->rows($statement) as $values) {
                    if (count($values) !== count($fields)) { throw new PurchaseSellMismatch('Backup row columns do not match its table definition.'); }
                    $row = array_combine($fields, $values);
                    if ($table === 'transactions' && in_array((int) ($row['id'] ?? 0), [$transferId, $receiptId], true)) {
                        if (isset($transactions[(int) $row['id']])) { throw new PurchaseSellMismatch('The backup contains duplicate transaction IDs.'); }
                        $transactions[(int) $row['id']] = array_intersect_key($row, array_flip(['id', 'business_id', 'location_id', 'type', 'status', 'ref_no', 'transfer_parent_id', 'transaction_date']));
                    }
                    if ($table === 'purchase_lines' && (int) ($row['transaction_id'] ?? 0) === $receiptId) {
                        if (count($receipts) >= 5000) { throw new PurchaseSellMismatch('Restore at most 5000 receiving lots in one operation.'); }
                        $receipts[] = $row;
                    }
                }
            }
        } finally { fclose($stream); }
        if (!isset($transactions[$transferId], $transactions[$receiptId]) || !$receipts) {
            throw new PurchaseSellMismatch('This backup does not contain both transfer documents and their original receiving lots. Select an older complete SQL export.');
        }
        return ['sha256' => hash_file('sha256', $path), 'transactions' => $transactions, 'receipts' => $receipts];
    }

    private function rows(string $sql): \Generator
    {
        $offset = 0; $length = strlen($sql); $row = null; $expectValue = false; $expectRow = true;
        while ($offset < $length) {
            if (!preg_match('/\G\s*(\x27(?:[^\x27\\\\]|\\\\.|\x27\x27)*\x27|NULL|[-+]?\d+(?:\.\d+)?(?:[eE][-+]?\d+)?|[(),;])/s', $sql, $match, 0, $offset)) {
                if (trim(substr($sql, $offset)) === '') { break; }
                throw new PurchaseSellMismatch('Unsupported SQL value in the backup. The file was not executed.');
            }
            $offset += strlen($match[0]); $token = $match[1];
            if ($token === '(' && $row === null && $expectRow) { $row = []; $expectValue = true; $expectRow = false; continue; }
            if ($token === ')' && $row !== null && !$expectValue) { yield $row; $row = null; continue; }
            if ($token === ',') {
                if ($row === null && !$expectRow) { $expectRow = true; continue; }
                if ($row !== null && !$expectValue) { $expectValue = true; continue; }
            }
            if ($token === ';' && $row === null && !$expectRow && trim(substr($sql, $offset)) === '') { return; }
            if ($row === null || !$expectValue || in_array($token, ['(', ')', ',', ';'], true)) {
                throw new PurchaseSellMismatch('The SQL backup row is malformed.');
            }
            $value = $token === 'NULL' ? null : $token;
            if ($token[0] === "'") {
                $value = preg_replace_callback('/\\\\(.)/s', fn ($m) => ['0' => "\0", 'n' => "\n", 'r' => "\r", 'b' => "\x08", 't' => "\t", 'Z' => "\x1a"][$m[1]] ?? $m[1], str_replace("''", "'", substr($token, 1, -1)));
            }
            $row[] = $value; $expectValue = false;
        }
        throw new PurchaseSellMismatch('The SQL backup INSERT is incomplete.');
    }
}
