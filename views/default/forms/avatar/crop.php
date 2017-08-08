<?php
/**
 * Avatar crop form
 *
 * @uses $vars['entity']
 */

$entity = elgg_extract('entity', $vars);
if (!$entity instanceof \ElggEntity) {
	return;
}

elgg_load_js('jquery.imgareaselect');
elgg_load_js('elgg.avatar_cropper');
elgg_load_css('jquery.imgareaselect');

$master_img = elgg_view('output/img', [
	'src' => $vars['entity']->getIconUrl('master'),
	'alt' => elgg_echo('avatar'),
	'class' => 'mrl',
	'id' => 'user-avatar-cropper',
]);

$preview_img = elgg_view('output/img', [
	'src' => $vars['entity']->getIconUrl('master'),
	'alt' => elgg_echo('avatar'),
]);

?>
<div class="clearfix">
	<?php echo $master_img; ?>
	<div id="user-avatar-preview-title"><label><?php echo elgg_echo('avatar:preview'); ?></label></div>
	<div id="user-avatar-preview"><?php echo $preview_img; ?></div>
</div>
<?php

$coords = ['x1', 'x2', 'y1', 'y2'];
foreach ($coords as $coord) {
	echo elgg_view_field([
		'#type' => 'hidden',
		'name' => $coord,
		'value' => $entity->$coord,
	]);
}

echo elgg_view_field([
	'#type' => 'hidden',
	'name' => 'guid',
	'value' => $entity->guid,
]);


$footer = elgg_view_field([
	'#type' => 'submit',
	'value' => elgg_echo('avatar:create'),
]);

elgg_set_form_footer($footer);
