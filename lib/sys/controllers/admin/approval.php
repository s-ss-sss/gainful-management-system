<?php

require_once ROOT_PATH . '/lib/sys/modules/daos/admin/ApprovalDao.class.php';
require_once ROOT_PATH . '/lib/sys/modules/admin/Approval.class.php';

// 名前空間
use App\Admin\Dao\ApprovalDao;
use App\Admin\Approval;

// インスタンス生成（依存性注入）
$approvalDao	= new ApprovalDao($db);
$approval		= new Approval($approvalDao);

// URLパラメータ
$path1	= getPathInfo(0) ?: 'default';
$path2	= getPathInfo(1);

// POSTパラメータ
$state = $_POST['state'] ?? 'default';

switch ($path1) {

	// 却下
	case 'reject':
		validPathInfo(2);
		switch ($state) {
			case 'reject'	: $approval->rejectApproval($path2);			break;	// 実行
			default			: $approval->displayApprovalRejectForm($path2);	break;	// フォーム
		}
		break;

	// 一覧
	default:
		validPathInfo(1);
		switch ($state) {
			case 'approve'	: $approval->approveRequests();			break;	// 承認
			default			: $approval->displayApprovalIndex();	break;	// 一覧
		}
		break;
}
