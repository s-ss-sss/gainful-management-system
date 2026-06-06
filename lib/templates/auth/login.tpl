<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>{$SITE_NAME}</title>
    <meta name="description" content="{$SITE_NAME}のログイン画面です。">
    <link rel="stylesheet" href="{$BASE_URL}css/style.css" type="text/css">
</head>
<body>
    <div class="u-container">
        <div class="u-inner">
            <main>
                <section class="login">
                    <div class="login__card-wrap">
                        <div class="login__card">
                            <h1 class="login__title u-mb-32">{$SITE_NAME}</h1>
                            <form action="/gainful/login/" method="POST">

                                {* CSRFトークン *}
                                <input type="hidden" name="csrf_token" value="{$csrf_token}">

                                {* メールアドレス *}
                                <div class="login__field">
                                    <label class="login__label u-mb-4">メールアドレス</label>
                                    <div class="login__control login__control--mail">
                                        <input
                                            type="email"
                                            name="email"
                                            value="{$form_data.email}"
                                            class="login__input {if isset($errors.email) || isset($errors.auth)}error-form{/if}"
                                            placeholder="メールアドレスを入力してください"
                                        >
                                    </div>
                                    {if isset($errors.email)}
                                        <p class="error-text u-mt-8">{$errors.email}</p>
                                    {/if}
                                </div>

                                {* パスワード *}
                                <div class="login__field u-mt-12">
                                    <label class="login__label u-mb-4">パスワード</label>
                                    <div class="login__control login__control--password">
                                        <input
                                            type="password"
                                            name="password"
                                            value=""
                                            class="login__input {if isset($errors.password) || isset($errors.auth)}error-form{/if}"
                                            placeholder="パスワードを入力してください"
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

                                {* ボタン *}
                                <button type="submit" class="button button--main login__button u-mt-32">ログイン</button>
                            </form>
                        </div>

                        {* 全体エラー *}
                        {if $warning}
                            {foreach from=$warning item=msg}
                                <p class="error-text u-mt-12">{$msg}</p>
                            {/foreach}
                        {/if}
                    </div>
                </section>
            </main>
        </div>
    </div>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://code.jquery.com/ui/1.13.2/jquery-ui.min.js"></script>
    <link rel="stylesheet" href="https://code.jquery.com/ui/1.13.2/themes/base/jquery-ui.css">
    <script src="{$BASE_URL}js/datepicker-ja.js" type="text/javascript"></script>
    <script src="{$BASE_URL}js/script.js" type="text/javascript"></script>
</body>
</html>
