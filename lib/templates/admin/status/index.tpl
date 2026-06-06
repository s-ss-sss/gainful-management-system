{extends file="admin/layout.tpl"}

{block name=content}
    <section class="u-mb-32">
        <div class="heading u-mb-24">
            <h2 class="heading__title u-mb-16">承認状況</h2>
            {if $bundle_count > 0}
                <h3 class="heading__title heading__title--red u-mb-16">
                    【承認済みが{$bundle_count}件あります】
                </h3>
                <p class="heading__text">
                    承認を差し戻す場合は<br>
                    「取消」を押してください
                </p>
            {else}
                <h3 class="heading__title heading__title--red">【承認済みはありません】</h3>
            {/if}
        </div>

        {* 全体エラー *}
        {if isset($warning)}
            {foreach from=$warning item=msg}
                <p class="error-text u-mb-16">{$msg}</p>
            {/foreach}
        {/if}

        <form method="POST" action="{$BASE_URL}admin/status/">

            {* state *}
            <input type="hidden" name="state" value="cancel">

            {* CSRFトークン *}
            <input type="hidden" name="csrf_token" value="{$csrf_token}">

            {* 承認待ちテーブル *}
            {if $approval_lists|@count > 0}
                {foreach from=$approval_lists item=user}
                    <div class="table__wrap u-mb-24">
                        <h3 class="table__user-name">{$user.user_name}</h3>
                        <table class="table">
                            <colgroup>
                                <col style="width: 18%;">
                                <col style="width: 14%;">
                                <col style="width: auto;">
                                <col style="width: 8%;">
                                <col style="width: 8%;">
                                <col style="width: auto;">
                            </colgroup>
                            <thead class="table__thead">
                                <tr class="table__tr">
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

                                        {* 取消 *}
                                        <td class="table__td table__td--action">
                                            <button
                                                type="submit"
                                                class="button button--sub button--sm js-cancel-button"
                                                data-id="{$b.bundle_id}"
                                            >取消</button>
                                        </td>
                                    </tr>
                                {/foreach}
                            </tbody>
                        </table>
                    </div>
                {/foreach}
            {/if}
        </form>
    </section>
{/block}

{block name=footer_script}
    {include file="common/alert.tpl"}
{/block}
