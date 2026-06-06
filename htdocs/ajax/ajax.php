<?php

$root_path = realpath(__DIR__ . '/../..');
require_once $root_path . '/lib/sys/common.php';
initApp($root_path);

require_once ROOT_PATH . '/lib/sys/modules/daos/CommonDao.class.php';
require_once ROOT_PATH . '/lib/sys/modules/Common.class.php';

// 年月を取得
$year	= (int)($_POST['year'] ?? date('Y'));
$month	= (int)($_POST['month'] ?? date('n'));

// 申請データ取得
$dao	= new CommonDao($db);
$data	= $dao->getApplyRequestsByMonth($year, $month);

// JSONで送信
header('Content-Type: application/json; charset=utf-8');
echo json_encode($data, JSON_UNESCAPED_UNICODE);
exit;
