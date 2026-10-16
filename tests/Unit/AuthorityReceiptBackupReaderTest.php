<?php

namespace Tests\Unit;

use App\Exceptions\PurchaseSellMismatch;
use App\Services\AuthorityReceiptBackupReader;
use PHPUnit\Framework\TestCase;

class AuthorityReceiptBackupReaderTest extends TestCase
{
    private array $files = [];

    protected function tearDown(): void
    {
        foreach ($this->files as $path) { unlink($path); }
        parent::tearDown();
    }

    private function backup(string $sql): string
    {
        $path = tempnam(sys_get_temp_dir(), 'pos-receipt-reader-');
        $this->files[] = $path;
        file_put_contents($path, $sql);
        return $path;
    }

    public function test_reads_multiline_explicit_insert_rows_and_never_executes_other_sql(): void
    {
        $path = $this->backup(<<<'SQL'
DROP TABLE IF EXISTS `purchase_lines`;
INSERT INTO `transactions` (`id`, `type`) VALUES
(10, 'sell_transfer'),
(11, 'purchase_transfer'),
(12, 'sell');
INSERT INTO `purchase_lines` (`id`, `transaction_id`, `lot_number`, `quantity`) VALUES
(20,11,'LOT\'A; DROP TABLE transactions; --',1.0000),
(21,11,'ខ្មែរ\\B\nC',2.0000),
(22,12,'OTHER',9);
SQL);
        $result = (new AuthorityReceiptBackupReader)->read($path, 10, 11);
        $this->assertCount(2, $result['transactions']);
        $this->assertCount(2, $result['receipts']);
        $this->assertSame("LOT'A; DROP TABLE transactions; --", $result['receipts'][0]['lot_number']);
        $this->assertSame("ខ្មែរ\\B\nC", $result['receipts'][1]['lot_number']);
        $this->assertSame(hash_file('sha256', $path), $result['sha256']);
    }

    public function test_reads_mysqldump_column_definitions_and_nulls(): void
    {
        $path = $this->backup(<<<'SQL'
CREATE TABLE `transactions` (
  `id` int NOT NULL,
  `type` varchar(100)
) ENGINE=InnoDB;
CREATE TABLE `purchase_lines` (
  `id` int NOT NULL,
  `transaction_id` int NOT NULL,
  `lot_number` varchar(191),
  `quantity` decimal(22,4)
) ENGINE=InnoDB;
INSERT INTO `transactions` VALUES (10,'sell_transfer'),(11,'purchase_transfer');
INSERT INTO `purchase_lines` VALUES (20,11,NULL,1.0000);
SQL);
        $result = (new AuthorityReceiptBackupReader)->read($path, 10, 11);
        $this->assertNull($result['receipts'][0]['lot_number']);
        $this->assertSame('1.0000', $result['receipts'][0]['quantity']);
    }

    public function test_rejects_sql_expressions_instead_of_executing_them(): void
    {
        $path = $this->backup("INSERT INTO `transactions` (`id`) VALUES (10),(11);\nINSERT INTO `purchase_lines` (`id`, `transaction_id`, `quantity`) VALUES (20,11,SLEEP(20));\n");
        $this->expectException(PurchaseSellMismatch::class);
        (new AuthorityReceiptBackupReader)->read($path, 10, 11);
    }

    public function test_rejects_backup_without_original_receiving_document(): void
    {
        $path = $this->backup("INSERT INTO `transactions` (`id`) VALUES (10);\nINSERT INTO `purchase_lines` (`id`, `transaction_id`) VALUES (20,11);\n");
        $this->expectException(PurchaseSellMismatch::class);
        (new AuthorityReceiptBackupReader)->read($path, 10, 11);
    }
}
