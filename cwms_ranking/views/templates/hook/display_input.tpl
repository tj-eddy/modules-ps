<div class="row">
    <fieldset class="col-md-4 form-group">
        <label class="form-control-label">
            {l s="Bonus Ranking" mod="cwms_ranking"}
            <span class="help-box"
                  data-toggle="popover"
                  data-content="{l s={$notice_message} mod="cwms_ranking"}">
            </span>
        </label>
        <input type="number" min="0" max="100"  value="{$bonus_ranking}" name="bonus_ranking"  class="form-control">
    </fieldset>
</div>