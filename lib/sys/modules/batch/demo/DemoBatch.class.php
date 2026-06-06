<?php

// 名前空間
namespace App\Batch\Demo;

class DemoBatch {

	private $today, $dao;

	/**
	 * コンストラクタ
	 *
	 * @access	public
	 * @param	$today, $dao
	 * @return
	 */
	public function __construct($today, $dao) {
		$this->today	= $today;
		$this->dao		= $dao;
	}

	/**
	 * バッチ処理実行
	 *
	 * @access	public
	 * @param
	 * @return
	 */
	public function run() {

		// ログ開始
		$start_time = microtime(true);
		$this->_log('Demo Batch Start');

		// トランザクション開始
		$this->dao->BeginTrans();

		try {
			// デモデータ削除
			$this->dao->deleteDemoData();
			$this->_log('Delete Demo Data Done');

			// デモデータ再投入
			$result = $this->dao->insertDemoSeedData($this->today);
			$this->_log("Insert Users {$result['users']} rows");
			$this->_log("Insert Special {$result['special']} rows");
			$this->_log("Insert Request {$result['request']} rows");
			$this->_log("Insert Compday {$result['compday']} rows");

			// トランザクション成功
			$this->dao->CommitTrans();

			// ログ終了
			$execution_time = round(microtime(true) - $start_time, 3);
			$this->_log("Demo Batch End ({$execution_time}s)");

		} catch (Exception $e) {

			// トランザクション失敗
			$this->dao->RollbackTrans();
			error_log('[Demo Batch Exception] ' . $e->getMessage(), 3, PHP_ERROR_LOG);
			throw $e;
		}
	}

	/**
	 * ログ出力
	 *
	 * @access	public
	 * @param	$message
	 * @return
	 */
	private function _log($message) {

		// ログ出力ファイル
		$file = ROOT_PATH . '/logs/batch.log';

		// ログ出力メッセージ
		$log = '[' . date('Y-m-d H:i:s') . "] {$message}" . PHP_EOL;

		// 画面出力
		echo $log;

		// ファイル出力
		file_put_contents($file, $log, FILE_APPEND | LOCK_EX);
	}
}
