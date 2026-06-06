<div class="balance__wrap u-mb-24">
    <ul class="balance u-mb-12">

        {* 有休 *}
        <li class="balance__item-wrap">
            <div class="balance__item">
                <div class="balance__main">
                    <span class="balance__label">
                        有休残日数 {$summary.holiday.balance|number_format:1}日
                    </span>
                    {if $summary.holiday.pending > 0}
                        <span class="balance__note">
                            （申請中 {$summary.holiday.pending|number_format:1}日）
                        </span>
                    {/if}
                </div>
            </div>
        </li>

        {* 代休 *}
        <li class="balance__item-wrap">
            <div class="balance__item">
                <div class="balance__main">
                    <span class="balance__label">
                        代休残日数 {$summary.compday.balance|number_format:1}日
                    </span>
                    {if $summary.compday.pending > 0}
                        <span class="balance__note">
                            （申請中 {$summary.compday.pending|number_format:1}日）
                        </span>
                    {/if}
                </div>
            </div>
        </li>

        {* 特別休暇 *}
        <li class="balance__item-wrap">
            <div class="balance__item">
                <div class="balance__main">
                    <span class="balance__label">
                        特別休暇残日数 {$summary.special.balance|number_format:1}日
                    </span>
                    {if $summary.special.pending > 0}
                        <span class="balance__note">
                            （申請中 {$summary.special.pending|number_format:1}日）
                        </span>
                    {/if}
                </div>

                {* 内訳 *}
                {if $summary.special.details|@count > 1}
                    <ul class="balance__details u-mt-4">
                        {foreach from=$summary.special.details item=d}
                            <li class="balance__detail">
                                - {$d.label} {$d.balance|number_format:1}日
                                {if $d.pending > 0}
                                    （申請中 {$d.pending|number_format:1}日）
                                {/if}
                            </li>
                        {/foreach}
                    </ul>
                {/if}
            </div>
        </li>
    </ul>
    <p class="balance__text">※各残日数は申請中の日数も含みます。ご注意ください。</p>
</div>
