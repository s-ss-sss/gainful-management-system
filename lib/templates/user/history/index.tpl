{extends file="user/layout.tpl"}

{block name=content}
	<section class="u-mb-32">
		<div class="heading u-mb-24">
			<h2 class="heading__title u-mb-16">休暇実績</h2>
			<p class="heading__text">
				表示年度を選択してください
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

		{* 表示選択フォーム *}
		<form action="{$BASE_URL}/history/" method="POST" class="history__filter u-mb-16">

			{* state *}
			<input type="hidden" name="state" value="history">

			{* CSRFトークン *}
			<input type="hidden" name="csrf_token" value="{$csrf_token}">

			<div class="history__filter-inner">
				<div class="history__filter-field">
					<div class="history__filter-wrap">
						<div class="history__filter-group">
							<span class="history__filter-label">表示年度</span>
							<select name="year" class="history__filter-select" onchange="this.form.submit()">
								{foreach from=$years item=y}
									<option
										value="{$y}"
										{if $y == $selected_year}selected{/if}
									>{$y}年</option>
								{/foreach}
							</select>
						</div>
					</div>
				</div>
			</div>
		</form>

		{* 申請テーブル *}
		<div class="table__wrap">
			<table class="table">
				<colgroup>
					<col style="width: 50px;">
					<col style="width: 18%;">
					<col style="width: auto;">
					<col style="width: auto;">
					<col style="width: 8%;">
					<col style="width: 8%;">
					<col style="width: 8%;">
				</colgroup>
				<thead class="table__thead">
					<tr class="table__tr">
						<th class="table__th">月</th>
						<th class="table__th">取得日</th>
						<th class="table__th">種別</th>
						<th class="table__th">事由</th>
						<th class="table__th">有休</th>
						<th class="table__th">代休</th>
						<th class="table__th">特別休暇</th>
					</tr>
				</thead>

				{* テーブル本文 *}
				<tbody class="table__tbody">

					{foreach from=$history key=month item=data}

						{* データのない月は空 *}
						{if $data.bundles|@count == 0}
							<tr class="table__tr">
								<td class="table__td u-text-center">{$month}</td>
								<td class="table__td"></td>
								<td class="table__td"></td>
								<td class="table__td"></td>
								<td class="table__td u-text-center">0.0</td>
								<td class="table__td u-text-center">0.0</td>
								<td class="table__td u-text-center">0.0</td>
							</tr>
						{else}

							{* bundle_id毎にループ *}
							{foreach from=$data.bundles item=b}
								<tr class="table__tr">

									{* bundle_idの数に応じたrowspan *}
									{if $b@first}
										 <td rowspan="{$data.bundles|@count}" class="table__td u-text-center">
											{$month}
										</td>
									{/if}

									{* 申請日 *}
									<td class="table__td">
										<ul class="table__list">
											{foreach from=$b.dates item=d}
												<li class="table__item">{$d}</li>
											{/foreach}
										</ul>
									</td>

									{* 種別 *}
									<td class="table__td">{$b.kind}</td>

									{* 事由 *}
									<td class="table__td">{$b.reason|nl2br nofilter}</td>

									{* bundle_idの数に応じたrowspan *}
									{if $b@first}

										{* 有休 *}
										<td rowspan="{$data.bundles|@count}" class="table__td u-text-center">
											{$data.summary.holiday|number_format:1}
										</td>

										{* 代休 *}
										<td rowspan="{$data.bundles|@count}" class="table__td u-text-center">
											{$data.summary.compday|number_format:1}
										</td>

										{* 特別休暇 *}
										<td rowspan="{$data.bundles|@count}" class="table__td u-text-center">
											{$data.summary.special|number_format:1}
										</td>
									{/if}
								</tr>
							{/foreach}
						{/if}
					{/foreach}
				</tbody>
			</table>
		</div>
	</section>
{/block}
