<?php
$pos = new PDO('mysql:host=127.0.0.1;port=3306;dbname=pos6.12;charset=utf8mb4', 'root', '090569070oK$');
echo "=== variations columns ===\n";
print_r($pos->query("DESCRIBE variations")->fetchAll(PDO::FETCH_ASSOC));
echo "\n=== try resolve for HR id=110 (imei 358649253624698) ===\n";
$sample = ['358649253624698','352164239567090','43306330643122','H953HKQW5X','MG953LL/A','MG9J3LL/A','A3063','A3526'];
foreach ($sample as $s) {
    $r = $pos->prepare("SELECT v.id, v.sub_sku, v.name, p.name pname, p.sku FROM variations v LEFT JOIN products p ON p.id=v.product_id WHERE v.sub_sku=? OR v.name=? OR p.sku=? LIMIT 3");
    $r->execute([$s,$s,$s]);
    $rows = $r->fetchAll(PDO::FETCH_ASSOC);
    echo "$s -> " . count($rows) . " matches\n";
    foreach (array_slice($rows,0,2) as $rr) echo "    " . json_encode($rr) . "\n";
}