{extends file="admin/layout.tpl"}

{block name=content}
	<section class="u-mb-32">
		<div class="heading u-mb-24">
			<h2 class="heading__title u-mb-16">申請データ管理</h2>
			<p class="heading__text">
				表示年度と表示対象を選択後<br>
				「表示する」を押してください
			</p>
		</div>

		{* 全体エラー *}
		{if isset($warning)}
			{foreach from=$warning item=msg}
				<p class="error-text u-mb-16">{$msg}</p>
			{/foreach}
		{/if}

		{* 表示選択フォーム *}
		<form action="{$BASE_URL}admin/request/" method="POST" class="history__filter u-mb-24">

			{* state *}
			<input type="hidden" name="state" value="request">

			{* CSRFトークン *}
			<input type="hidden" name="csrf_token" value="{$csrf_token}">

			<div class="history__filter-inner">
				<div class="history__filter-group">
					<span class="history__filter-label">表示年度</span>
					<select name="year" class="history__filter-select">
							{foreach from=$years item=y}
								<option
									value="{$y}"
									{if $y == $selected_year}selected{/if}
								>{$y}年
								</option>
							{/foreach}
					</select>
				</div>
				<div class="history__filter-wrap">
					<div class="history__filter-group">
						<span class="history__filter-label">表示対象</span>
						<select name="user" class="history__filter-select">
							<option value="">全員</option>
							{foreach from=$users item=u}
								<option
									value="{$u.user_id}"
									{if $u.user_id == $selected_user}selected{/if}
								>{$u.name}
								</option>
							{/foreach}
						</select>
					</div>
					<button type="submit" class="button button--main">表示する</button>
				</div>
			</div>
		</form>

		<form action="{$BASE_URL}admin/request/" method="POST">

			{* state *}
			<input type="hidden" name="state" value="delete">

			{* CSRFトークン *}
			<input type="hidden" name="csrf_token" value="{$csrf_token}">

			{* 申請データテーブル *}
			{if $requests_lists|@count > 0}
				{foreach from=$requests_lists item=user}
					<div class="table__wrap u-mb-24">
						<h3 class="table__user-name">{$user.user_name}</h3>
						<table class="table">
							<colgroup>
								<col style="width: 18%;">
								<col style="width: 18%;">
								<col style="width: 26%;">
								<col style="width: 8%;">
								<col style="width: 8%;">
								<col style="width: 8%;">
								<col style="width: auto;">
								<col style="width: auto;">
							</colgroup>
							<thead class="table__thead">
								<tr class="table__tr">
									<th class="table__th">取得日</th>
									<th class="table__th table__th--kind">種別</th>
									<th class="table__th">事由</th>
									<th class="table__th">有休</th>
									<th class="table__th">代休</th>
									<th class="table__th">特別休暇</th>
									<th colspan="2" class="table__th table__th--action">操作</th>
								</tr>
							</thead>
							<tbody class="table__tbody">
								{foreach from=$user.bundles item=b}
									<tr class="table__tr">

										{* 取得日 *}
										<td class="table__td">
											<ul class="table__list">
												{foreach from=$b.dates item=d}
													<li class="table__item">{$d}</li>
												{/foreach}
											</ul>
										</td>

										{* 種別 *}
										<td class="table__td table__th--kind">
											{$b.kind}
										</td>

										{* 事由 *}
										<td class="table__td">
											{$b.reason|nl2br nofilter}
										</td>

										{* 有休 *}
										<td class="table__td u-text-center">
											{$b.holiday|number_format:1}
										</td>

										{* 代休 *}
										<td class="table__td u-text-center">
											{$b.compday|number_format:1}
										</td>

										{* 特別休暇 *}
										<td class="table__td u-text-center">
											{$b.special|number_format:1}
										</td>

										{* 修正 *}
										<td class="table__td table__td--action">
											<a href="{$BASE_URL}admin/request/edit/{$b.bundle_id}/" class="button button--sub button--sm">修正</a>
										</td>

										{* 削除 *}
										<td class="table__td table__td--action">
											<button
												type="submit"
												class="button button--red button--sm js-delete-button"
												data-id="{$b.bundle_id}"
											>削除</button>
										</td>
									</tr>
								{/foreach}
							</tbody>
						</table>
					</div>
				{/foreach}
			{else}
				<h3 class="table__no-data u-mb-24">【申請データはありません】</h3>
			{/if}

			{* ボタン群 *}
			{if $add_flg}
				<div class="button__wrap">
					<a href="{$BASE_URL}admin/request/create/{$selected_user}/" class="button button--main">追加する</a>
				</div>
			{/if}
		</form>
	</section>
{/block}

{block name=footer_script}
	{include file="common/alert.tpl"}
{/block}
