{extends file="admin/layout.tpl"}

{block name=content}
	<section class="u-mb-32">
		<div class="heading u-mb-24">
			<h2 class="heading__title u-mb-16">ユーザー管理</h2>
			<p class="heading__text">
				内容を入力後<br>
				「登録する」を押してください
			</p>
		</div>

		{* 全体エラー *}
		{if isset($warning)}
			{foreach from=$warning item=msg}
				<p class="error-text u-mb-16">{$msg}</p>
			{/foreach}
		{/if}

		<form action="{$BASE_URL}admin/user/create/" method="POST">

			{* state *}
			<input type="hidden" name="state" value="create">

			{* CSRFトークン *}
			<input type="hidden" name="csrf_token" value="{$csrf_token}">

			<div class="form-block__group">

				{* ID *}
				<div class="form-block__field">
					<label class="form-block__label">ID</label>
					<div class="form-block__input-wrap">
						<input
							type="text"
							name="user_id"
							value="{$form_data.user_id}"
							class="form-block__input {if isset($errors.user_id)}error-form{/if}"
						>
						{if isset($errors.user_id)}
							<p class="error-text u-mt-8">{$errors.user_id}</p>
						{/if}
					</div>
				</div>

				{* 氏名 *}
				<div class="form-block__field">
					<label class="form-block__label">氏名</label>
					<div class="form-block__input-wrap">
						<input
							type="text"
							name="name"
							value="{$form_data.name}"
							class="form-block__input {if isset($errors.name)}error-form{/if}"
						>
						{if isset($errors.name)}
							<p class="error-text u-mt-8">{$errors.name}</p>
						{/if}
					</div>
				</div>

				{* 入社年月日 *}
				<div class="form-block__field">
					<label class="form-block__label">入社年月日</label>
					<div class="form-block__input-wrap">
						<div class="form-block__datepicker datepicker-wrap">
							<input
								type="text"
								name="join_date"
								value="{$form_data.join_date}"
								class="form-block__input js-datepicker {if isset($errors.join_date)}error-form{/if}"
								readonly
							>
						</div>
						{if isset($errors.join_date)}
							<p class="error-text u-mt-8">{$errors.join_date}</p>
						{/if}
					</div>
				</div>

				{* 所属 *}
				<div class="form-block__field">
					<label class="form-block__label">所属</label>
					<div class="form-block__input-wrap">
						<select
							name="auth"
							class="form-block__select {if isset($errors.auth)}error-form{/if}"
						>
							<option value="">選択してください</option>
							{foreach from=$apply_auth key=num item=auth}
								<option
									value="{$num}"
									{if $form_data.auth == $num}selected{/if}
								>{$auth}</option>
							{/foreach}
						</select>
						{if isset($errors.auth)}
							<p class="error-text u-mt-8">{$errors.auth}</p>
						{/if}
					</div>
				</div>

				{* メールアドレス *}
				<div class="form-block__field">
					<label class="form-block__label">メールアドレス</label>
					<div class="form-block__input-wrap">
						<input
							type="text"
							name="mail"
							value="{$form_data.mail}"
							class="form-block__input {if isset($errors.mail)}error-form{/if}"
						>
						{if isset($errors.mail)}
							<p class="error-text u-mt-8">{$errors.mail}</p>
						{/if}
					</div>
				</div>

				{* パスワード *}
				<div class="form-block__field">
					<label for="password" class="form-block__label">パスワード</label>
					<div class="form-block__input-wrap">
						<div class="form-block__login login__control">
							<input
								type="password"
								name="password"
								value=""
								class="form-block__input {if isset($errors.password)}error-form{/if}"
							>
							<span class="login__toggle">
								<img
									src="{$BASE_URL}img/eye-open.svg"
									data-open="{$BASE_URL}img/eye-close.svg"
									data-close="{$BASE_URL}img/eye-open.svg"
									class="login__toggle-icon"
								>
							</span>
						</div>
						{if isset($errors.password)}
							<p class="error-text u-mt-8">{$errors.password}</p>
						{/if}
					</div>
				</div>

				{* 有休残日数 *}
				<div class="form-block__field">
					<label class="form-block__label">有休残日数</label>
					<div class="form-block__input-wrap">
						<div class="form-block__unit-wrap">
							<input
								type="number"
								step="0.5"
								name="holiday_number"
								value="{if is_numeric($form_data.holiday_number)}{$form_data.holiday_number|number_format:1}{/if}"
								class="form-block__input form-block__input--number js-half-input
									{if isset($errors.holiday_number)}error-form{/if}"
							>
							<span class="form-block__unit">日</span>
						</div>
						{if isset($errors.holiday_number)}
							<p class="error-text u-mt-8">{$errors.holiday_number}</p>
						{/if}
					</div>
				</div>

				{* 権限 *}
				{assign var=selected_position value=$form_data.position|default:0}
				<div class="form-block__field">
					<div class="form-block__label">権限</div>
					<div class="form-block__input-wrap">
						{foreach from=$apply_position key=num item=position}
							<label class="form-block__radio-wrap">
								<input
									type="radio"
									name="position"
									value="{$num}"
									class="form-block__radio {if isset($errors.position)}error-form{/if}"
									{if $selected_position == $num}checked{/if}
								>{$position}
							</label>
						{/foreach}
						{if isset($errors.position)}
							<p class="error-text u-mt-8">{$errors.position}</p>
						{/if}
					</div>
				</div>
			</div>

			{* ボタン群 *}
			<div class="button__wrap u-mt-24">
				<a href="{$BASE_URL}admin/user/" class="button button--sub">戻る</a>
				<button type="submit" class="button button--main">登録する</button>
			</div>
		</form>
	</section>
{/block}
