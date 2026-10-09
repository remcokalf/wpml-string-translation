<?php
class WPML_ST_Support_Info_Filter implements IWPML_Backend_Action, IWPML_DIC_Action {
	private $support_info;

	function __construct( WPML_ST_Support_Info $support_info ) {
		$this->support_info     = $support_info;
	}

	public function add_hooks() {
		add_filter( 'wpml_support_info_blocks', [ $this, 'filter_blocks' ] );
	}

	public function filter_blocks(array $blocks) {

		$is_mbstring_extension_loaded      = $this->support_info->is_mbstring_extension_loaded();
		/* translators: Value in the WPML support information table: the PHP extension is available. Past participle used as a state. */
		$mbstring_loaded_label             = __( 'Loaded', 'wpml-string-translation' );
		/* translators: Value in the WPML support information table: the PHP extension is not available. Past participle used as a state. */
		$mbstring_not_loaded_label         = __( 'Not loaded', 'wpml-string-translation' );
		$blocks['php']['data']['mbstring'] = array(
			'label'    => __( 'Multibyte String extension', 'wpml-string-translation' ),
			'value'    => $is_mbstring_extension_loaded ? $mbstring_loaded_label : $mbstring_not_loaded_label,
			'url'      => 'http://php.net/manual/book.mbstring.php',
			'messages' => array(
				__( 'Multibyte String extension is required for WPML String Translation.', 'wpml-string-translation' ) => \WPML\ST\OutboundLinks\OutboundLinks::to(
					'https://wpml.org/home/minimum-requirements/',
					array(
						'medium'   => 'support',
						'campaign' => 'requirements',
					)
				),
			),
			'is_error' => ! $is_mbstring_extension_loaded,
		);

		return $blocks;
	}
}
