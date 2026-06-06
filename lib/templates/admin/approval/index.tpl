{extends file="admin/layout.tpl"}

{block name=content}
	<section class="u-mb-32">
		<div class="heading u-mb-24">
			<h2 class="heading__title u-mb-16">休暇承認</h2>
			{if $bundle_count > 0}
				<h3 class="heading__title heading__title--red u-mb-16">
					【承認待ちが{$bundle_count}件あります】
				</h3>
				<p class="heading__text">
					問題がなければチェックを入れて<br>
					「承認する」を押してください
				</p>
			{else}
				<h3 class="heading__title heading__title--red">【承認待ちはありません】</h3>
			{/if}
		</div>

		{* 全体エラー *}
		{if isset($warning)}
			{foreach from=$warning item=msg}
				<p class="error-text u-mb-16">{$msg}</p>
			{/foreach}
		{/if}

		<form method="POST" action="{$BASE_URL}admin/">

			{* state *}
			<input type="hidden" name="state" value="approve">

			{* CSRFトークン *}
			<input type="hidden" name="csrf_token" value="{$csrf_token}">

			{* カレンダー *}
			<div id="js-single-calendar" class="calendar__wrap u-mb-32" data-readonly="true"></div>

			{* 承認待ちテーブル *}
			{if $approval_lists|@count > 0}
				{foreach from=$approval_lists item=user}
					<div class="table__wrap u-mb-24">
						<h3 class="table__user-name">{$user.user_name}</h3>
						<table class="table">
							<colgroup>
								<col style="width: 50px;">
								<col style="width: 18%;">
								<col style="width: 14%;">
								<col style="width: auto;">
								<col style="width: 8%;">
								<col style="width: 8%;">
								<col style="width: auto;">
							</colgroup>
							<thead class="table__thead">
								<tr class="table__tr">
									<th class="table__th table__th--checkbox">
										<label class="table__checkbox-label">
											<input type="checkbox" name="" class="table__checkbox js-all-check">
										</label>
									</th>
									<th class="table__th">取得日</th>
									<th class="table__th">種別</th>
									<th class="table__th">事由</th>
									<th class="table__th">所属長</th>
									<th class="table__th">社長</th>
									<th class="table__th table__th--action">操作</th>
								</tr>
							</thead>
							<tbody class="table__tbody">
								{foreach from=$user.bundles item=b}
									<tr class="table__tr">

										{* チェックボックス *}
										<td class="table__td table__td--checkbox">
											<label class="table__checkbox-label">
												<input type="checkbox" name="approval[]" value="{$b.bundle_id}" class="table__checkbox js-item-check">
											</label>
										</td>

										{* 取得日 *}
										<td class="table__td">
											<ul class="table__list">
												{foreach from=$b.dates item=d}
													<li class="table__item">{$d}</li>
												{/foreach}
											</ul>
										</td>

										{* 種別 *}
										<td class="table__td">
											{$b.kind}
										</td>

										{* 事由 *}
										<td class="table__td">
											{$b.reason|nl2br nofilter}
										</td>

										{* 所属長 *}
										<td class="table__td u-text-center">
											{if $b.boss_result == "0"}○{/if}
										</td>

										{* 社長 *}
										<td class="table__td u-text-center">
											{if $b.manager_result == "0"}○{/if}
										</td>

										{* 却下 *}
										<td class="table__td table__td--action">
											<a href="{$BASE_URL}admin/reject/{$b.bundle_id}/" class="button button--sub button--sm">却下</a>
										</td>
									</tr>
								{/foreach}
							</tbody>
						</table>
					</div>
				{/foreach}

				{* ボタン群 *}
				<div class="button__wrap">
					<button type="submit" class="button button--main js-approval-button">承認する</button>
				</div>
			{/if}
		</form>
	</section>
{/block}

{block name=footer_script}
	<script>
		const WEEKDAYS		= {$weekdays|@json_encode nofilter};
		const SECTIONS		= {$sections|@json_encode nofilter};
		const HOLIDAYS		= {$holidays|@json_encode nofilter};
		const currentYear	= {$current_year|escape:'javascript'};
		const currentMonth	= {$current_month|escape:'javascript'} - 1; // JSは0スタート
	</script>
	{include file="common/alert.tpl"}
{/block}
