<?php /* One shared pop-up for every "Add stock" button. assets/app.js fills in which item it is for. */ ?>
<dialog id="add-stock" aria-labelledby="add-stock-title">
    <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="restock">
        <input type="hidden" name="inventory_id" value="">
        <input type="hidden" name="back" value="<?= h(here()) ?>">
        <div class="dialog-head">
            <span class="dialog-icon"><?= icon('plus') ?></span>
            <div><h2 id="add-stock-title">Add stock</h2><p data-item-name></p></div>
        </div>
        <label>How many units arrived?<input type="number" name="quantity" min="1" required inputmode="numeric"></label>
        <label><span>Note <i>optional</i></span><input name="remarks" maxlength="255" placeholder="e.g. Delivery receipt DR-1042"></label>
        <div class="dialog-actions">
            <button type="button" class="btn btn-ghost" data-close>Cancel</button>
            <button class="btn"><?= icon('check') ?>Add to stock</button>
        </div>
    </form>
</dialog>
