<!DOCTYPE html>
<html lang="ja">
<head>
	<meta charset="UTF-8">
	<title>休暇申請システム</title>
	<link rel="stylesheet" href="{$BASE_URL}css/style.css" type="text/css">
</head>
<body>
	<div class="u-container">

		{* ヘッダー *}
		<header class="header u-mb-24">
			<div class="header__inner">
				<h1 class="header__title u-mb-12">休暇申請</h1>
				<nav class="header__nav u-inner" aria-label="メインメニュー">
					<ul class="header__nav-list">
						<li class="header__nav-item">
							<a href="{$BASE_URL}" class="header__nav-link"{if $section == 'apply'} aria-current="page"{/if}>休暇申請</a>
						</li>
						<li class="header__nav-item">
							<a href="{$BASE_URL}history/" class="header__nav-link"{if $section == 'history'} aria-current="page"{/if}>休暇実績</a>
						</li>
						<li class="header__nav-item">
							<a href="{$BASE_URL}status/" class="header__nav-link"{if $section == 'status'} aria-current="page"{/if}>申請状況</a>
						</li>
						{if $position == 1 || $position == 2}
							<li class="header__nav-item">
								<a href="{$BASE_URL}admin/" class="header__nav-link">管理メニュー</a>
							</li>
						{/if}
					</ul>
					<div class="header__nav-user">
						<span class="header__nav-name">{$user_name}</span>
						<a href="{$BASE_URL}logout/" class="header__nav-logout">ログアウト</a>
					</div>
				</nav>
			</div>
		</header>

		{* メイン *}
		<main class="u-inner">
			{block name=content}{/block}
		</main>

		{* フッター *}
		<footer class="footer">
			<small class="footer__copy">Copyright &copy; Gainful Demo All rights reserved．</small>
		</footer>
	</div>
	<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
	<script src="https://code.jquery.com/ui/1.13.2/jquery-ui.min.js"></script>
	<link rel="stylesheet" href="https://code.jquery.com/ui/1.13.2/themes/base/jquery-ui.css">
	<script src="{$BASE_URL}js/datepicker-ja.js"></script>
	<script src="{$BASE_URL}js/script.js"></script>
	{block name=footer_script}{/block}
</body>
</html>
