{extends file="user/layout.tpl"}

{block name=content}
	<section class="u-mb-32">
		<div class="heading u-mb-24">
			<h2 class="heading__title u-mb-16">申請状況</h2>
			<p class="heading__text">
				申請状況の一覧です
			</p>
		</div>

		{* 全体エラー *}
		{if isset($warning)}
			{foreach from=$warning item=msg}
				<p class="error-text u-mb-16">{$msg}</p>
			{/foreach}
		{/if}

		{* 残日数 *}
		{include file="user/components/balance.tpl"}

		<form action="{$BASE_URL}status/" method="POST">

			{* state *}
			<input type="hidden" name="state" value="cancel">

			{* CSRFトークン *}
			<input type="hidden" name="csrf_token" value="{$csrf_token}">

			{* 申請テーブル *}
			<div class="table__wrap">
				<table class="table">
					<colgroup>
						<col style="width: 18%;">
						<col style="width: auto;">
						<col style="width: auto;">
						<col style="width: 8%;">
						<col style="width: 8%;">
						<col style="width: 8%;">
						<col style="width: 8%;">
						<col style="width: 8%;">
						<col style="width: auto;">
					</colgroup>
					<thead class="table__thead">
						<tr class="table__tr">
							<th class="table__th">取得日</th>
							<th class="table__th">種別</th>
							<th class="table__th">事由</th>
							<th class="table__th">有休</th>
							<th class="table__th">代休</th>
							<th class="table__th">特別休暇</th>
							<th class="table__th">所属長</th>
							<th class="table__th">社長</th>
							<th class="table__th">操作</th>
						</tr>
					</thead>
					<tbody class="table__tbody">
						{if $status_lists|@count > 0}
							{foreach from=$status_lists item=bundle}
								<tr class="table__tr">

									{* 取得日 *}
									<td class="table__td">
										<ul class="table__list">
											{foreach from=$bundle.dates item=d}
												<li class="table__item">{$d}</li>
											{/foreach}
										</ul>
									</td>

									{* 種別 *}
									<td class="table__td">
										{$bundle.kind}
									</td>

									{* 事由 *}
									<td class="table__td">
										{$bundle.reason|nl2br nofilter}
									</td>

									{* 有休 *}
									<td class="table__td u-text-center">
										{$bundle.holiday|number_format:1}
									</td>

									{* 代休 *}
									<td class="table__td u-text-center">
										{$bundle.compday|number_format:1}
									</td>

									{* 特別休暇 *}
									<td class="table__td u-text-center">
										{$bundle.special|number_format:1}
									</td>

									{* 所属長 *}
									<td class="table__td u-text-center">
										{if $bundle.boss_result == "0"}○{/if}
									</td>

									{* 社長 *}
									<td class="table__td u-text-center">
										{if $bundle.manager_result == "0"}○{/if}
									</td>

									{* 取消 *}
									<td class="table__td table__td--action">
										<button
											type="submit"
											class="button button--sub button--sm js-cancel-button"
											data-id="{$bundle.bundle_id}"
										>取消</button>
									</td>
								</tr>
							{/foreach}
						{else}
							<tr class="table__tr">
								<td colspan="9" class="table__td u-text-center">申請中のデータはありません</td>
							</tr>
						{/if}
					</tbody>
				</table>
			</div>
		</form>
	</section>
{/block}

{block name=footer_script}
	{include file="common/alert.tpl"}
{/block}
