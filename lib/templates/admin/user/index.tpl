{extends file="admin/layout.tpl"}

{block name=content}
	<section class="u-mb-32">
		<div class="heading u-mb-24">
			<h2 class="heading__title u-mb-16">ユーザー管理</h2>
			<p class="heading__text">
				ユーザーを登録する場合は<br>
				「追加する」を押してください
			</p>
		</div>

		{* 全体エラー *}
		{if isset($warning)}
			{foreach from=$warning item=msg}
				<p class="error-text u-mb-16">{$msg}</p>
			{/foreach}
		{/if}

		<form action="{$BASE_URL}admin/user/" method="POST">

			{* state *}
			<input type="hidden" name="state" value="delete">

			{* CSRFトークン *}
			<input type="hidden" name="csrf_token" value="{$csrf_token}">

			{* ユーザーテーブル *}
			<div class="table__wrap u-mb-24">
				<table class="table">
					<colgroup>
						<col style="width: auto;">
						<col style="width: auto;">
						<col style="width: auto;">
						<col style="width: auto;">
						<col style="width: 14%;">
						<col style="width: auto;">
						<col style="width: auto;">
						<col style="width: auto;">
					</colgroup>
					<thead class="table__thead">
						<tr class="table__tr">
							<th class="table__th">ID</th>
							<th class="table__th">氏名</th>
							<th class="table__th">所属</th>
							<th class="table__th">メールアドレス</th>
							<th class="table__th">入社年月日</th>
							<th class="table__th">勤続年数</th>
							<th colspan="2" class="table__th table__th--action">操作</th>
						</tr>
					</thead>
					<tbody class="table__tbody">
						{foreach from=$users item=u}
							<tr class="table__tr">

								{* ID *}
								<td class="table__td">{$u.user_id}</td>

								{* 氏名 *}
								<td class="table__td">{$u.name}</td>

								{* 所属 *}
								<td class="table__td">{$u.auth_label}</td>

								{* メールアドレス *}
								<td class="table__td">{$u.mail}</td>

								{* 入社年月日 *}
								<td class="table__td">{$u.join_date_label}</td>

								{* 勤続年数 *}
								<td class="table__td">{$u.year_label}</td>

								{* 修正 *}
								<td class="table__td table__td--action">
									<a href="{$BASE_URL}admin/user/edit/{$u.user_id}/" class="button button--sub button--sm">修正</a>
								</td>

								{* 削除 *}
								<td class="table__td table__td--action">
									<button
										type="submit"
										class="button button--red button--sm js-delete-button"
										data-id="{$u.user_id}"
									>削除</button>
								</td>
							</tr>
						{/foreach}
					</tbody>
				</table>
			</div>

			{* ボタン群 *}
			<div class="button__wrap">
				<a href="{$BASE_URL}admin/user/create/" class="button button--main">追加する</a>
			</div>
		</form>
	</section>
{/block}

{block name=footer_script}
	{include file="common/alert.tpl"}
{/block}
