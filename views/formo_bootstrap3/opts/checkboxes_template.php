<div class="checkbox opts">
<?php foreach ($opts as $key => $opt): ?>
	<label>
		<input type="checkbox" name="<?=$field->name()?>[]" value="<?=$key?>" <?php if (in_array($key, $field->val())) echo ' checked="checked"'; ?> /> <span class="opt-label"><?=$opt?></span>
	</label>
<?php endforeach; ?>
</div>
