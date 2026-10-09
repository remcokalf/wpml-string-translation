<div class="wrap">
	<h2><?php echo __( 'Admin Texts Translation', 'wpml-string-translation' ); ?></h2>
	<?php
		$notices              = wpml_get_admin_notices();
		$noticeId             = 'AutoRegisterStringsNotice3';
	?>

	<div id="icl_st_option_writes">
		<div id="wpml-admin-text-options"/>

	</div>
	<p>
		<a href="<?php echo admin_url( 'admin.php?page=' . WPML_ST_FOLDER . '/menu/string-translation.php' ); ?>">
			&laquo; <?php echo wpml_bold_names( __( 'Return to <b>String Translation</b>', 'wpml-string-translation' ) ); ?>
		</a>
	</p>
</div>
