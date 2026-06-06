<?php

require_once ROOT_PATH . '/lib/sys/modules/daos/user/HistoryDao.class.php';
require_once ROOT_PATH . '/lib/sys/modules/user/History.class.php';

// 名前空間
use App\User\Dao\HistoryDao;
use App\User\History;

// インスタンス生成（依存性注入）
$historyDao	= new HistoryDao($db);
$history	= new History($historyDao);

// POSTパラメータ
$state = $_POST['state'] ?? 'default';

// パスチェック
validPathInfo(1);

// 一覧
$history->displayHistoryIndex();
