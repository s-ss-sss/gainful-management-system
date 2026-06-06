<?php

require_once ROOT_PATH . '/lib/sys/modules/daos/admin/HistoryDao.class.php';
require_once ROOT_PATH . '/lib/sys/modules/admin/History.class.php';

// 名前空間
use App\Admin\Dao\HistoryDao;
use App\Admin\History;

// インスタンス生成（依存性注入）
$historyDao	= new HistoryDao($db);
$history	= new History($historyDao);

// パスチェック
validPathInfo(1);

// 一覧
$history->displayHistoryIndex();
