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

require_once ROOT_PATH . '/lib/sys/modules/daos/batch/demo/DemoBatchDao.class.php';
require_once ROOT_PATH . '/lib/sys/modules/batch/demo/DemoBatch.class.php';

// 名前空間
use App\Batch\Demo\Dao\DemoBatchDao;
use App\Batch\Demo\DemoBatch;

// インスタンス生成（依存性注入）
$demoBatchDao	= new DemoBatchDao($db);
$demoBatch		= new DemoBatch($today, $demoBatchDao);

// バッチ処理実行
$demoBatch->run();
