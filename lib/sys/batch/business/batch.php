<?php

// CLI専用
if (php_sapi_name() !== 'cli') {
	exit('CLI only');
}

// 初期設定
$root_path = dirname(__DIR__, 4);
require_once $root_path . '/lib/sys/common.php';
initApp($root_path);

// 実行日
$today = new DateTimeImmutable('today');

// ログ開始
echo '[Business Batch Start] ' . $today->format('Y-m-d') . PHP_EOL;

require_once ROOT_PATH . '/lib/sys/modules/daos/batch/business/BusinessBatchDao.class.php';
require_once ROOT_PATH . '/lib/sys/modules/batch/business/BusinessBatch.class.php';

// 名前空間
use App\Batch\Business\Dao\BusinessBatchDao;
use App\Batch\Business\BusinessBatch;

// インスタンス生成（依存性注入）
$businessBatchDao	= new BusinessBatchDao($db);
$businessBatch		= new BusinessBatch($today, $businessBatchDao);

// バッチ処理実行
$businessBatch->run();

// ログ終了
echo '[Business Batch End]' . PHP_EOL;
