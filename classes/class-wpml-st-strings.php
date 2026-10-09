<?php

use WPML\API\Sanitize;
use WPML\ST\TranslationFile\StringCollation;
use WPML\ST\StringValue;

class WPML_ST_Strings {
	use StringCollation;

	const EMPTY_CONTEXT_LABEL = 'empty-context-domain';

	private $sitepress;
	private $wp_query;
	private $wpdb;

	private $has_text_usable = null;

	public function __construct( $sitepress, $wpdb, $wp_query ) {
		$this->wpdb      = $wpdb;
		$this->sitepress = $sitepress;
		$this->wp_query  = $wp_query;
	}

	public function get_string_translations() {
		$string_translations = array();

		$extra_cond = '';
		$joins      = [];

		$active_languages = $this->sitepress->get_active_languages();

		$status_filter = isset( $_GET['status'] ) ? (int) $_GET['status'] : false;

		$translation_priority = isset( $_GET['translation-priority'] ) ? $_GET['translation-priority'] : false;

		if ( false !== $status_filter ) {
			if ( ICL_TM_COMPLETE === $status_filter ) {
				$extra_cond .= ' AND s.status = ' . ICL_TM_COMPLETE;
			} elseif ( ICL_STRING_TRANSLATION_PARTIAL === $status_filter ) {
				$extra_cond .= ' AND s.status = ' . ICL_STRING_TRANSLATION_PARTIAL;
			} elseif ( ICL_STRING_TRANSLATION_STRING_TRACKING_TYPE_FRONTEND === $status_filter ) {
				$joins[] = "INNER JOIN {$this->wpdb->prefix}icl_string_positions string_positions ON s.id = string_positions.string_id";
				$extra_cond .= ' AND string_positions.kind = ' . ICL_STRING_TRANSLATION_STRING_TRACKING_TYPE_FRONTEND;
				$extra_cond .= ' AND s.status IN (' . ICL_STRING_TRANSLATION_PARTIAL . ',' . ICL_TM_NEEDS_UPDATE . ',' . ICL_TM_NOT_TRANSLATED . ',' . ICL_TM_WAITING_FOR_TRANSLATOR . ')';
			} elseif ( ICL_TM_WAITING_FOR_TRANSLATOR !== $status_filter ) {
				$extra_cond .= ' AND s.status IN (' . ICL_STRING_TRANSLATION_PARTIAL . ',' . ICL_TM_NEEDS_UPDATE . ',' . ICL_TM_NOT_TRANSLATED . ',' . ICL_TM_WAITING_FOR_TRANSLATOR . ')';
			}
		}

		if ( false !== $translation_priority ) {
			$esc_translation_priority = esc_sql( $translation_priority );
			if ( __( 'Optional', 'sitepress' ) === $translation_priority ) {
				$extra_cond .= " AND s.translation_priority IN ( '" . $esc_translation_priority . "', '' ) ";
			} else {
				$extra_cond .= " AND s.translation_priority = '" . $esc_translation_priority . "' ";
			}
		}

		$context = $this->get_context();

		if ( isset( $context ) ) {
			$extra_cond .= $this->wpdb->prepare( ' AND s.context = %s ', $context );
		}

		if ( $this->must_show_all_results() ) {
			$limit  = 9999;
			$offset = 0;
		} else {
			$limit         = $this->get_strings_per_page();
			$_GET['paged'] = isset( $_GET['paged'] ) ? $_GET['paged'] : 1;
			$offset        = ( $_GET['paged'] - 1 ) * $limit;
		}

		$search_filter = $this->get_search_filter();

		$sql_query = ' WHERE 1 ';
		if ( ICL_TM_WAITING_FOR_TRANSLATOR === $status_filter ) {
			$sql_query .= ' AND s.status = ' . ICL_TM_WAITING_FOR_TRANSLATOR;
		} elseif ( $active_languages && $search_filter && ! $this->must_show_all_results() ) {
			$sql_query .= ' AND ' . $this->get_value_search_query();
			$joins[]    = "LEFT JOIN {$this->wpdb->prefix}icl_string_translations str ON str.string_id = s.id";
		}

		$excluded_package_condition = $this->get_excluded_string_package_condition();

		if ( '' !== $excluded_package_condition || $this->is_troubleshooting_filter_enabled() ) {
			$joins[] = ' LEFT JOIN  ' . $this->wpdb->prefix . 'icl_string_packages sp ON sp.ID = s.string_package_id';

			if ( '' !== $excluded_package_condition ) {
				$sql_query .= $excluded_package_condition;
			}

			if ( $this->is_troubleshooting_filter_enabled() ) {
				$joins[] = ' INNER JOIN  ' . $this->wpdb->prefix . "icl_translate it ON it.field_data_translated = sp.name and it.field_type='original_id'";
			}
		}

		$res = $this->get_results( $sql_query, $extra_cond, $offset, $limit, $joins );

		if ( $res ) {
			$extra_cond = '';
			if ( isset( $_GET['translation_language'] ) ) {
				$translation_language = esc_sql( $_GET['translation_language'] );
				$extra_cond          .= " AND language='" . $translation_language . "'";
			}

			foreach ( $res as $row ) {
				$string_translations[ $row['string_id'] ] = $row;

				$tr = $this->wpdb->get_results(
					$this->wpdb->prepare(
						"
							SELECT id, language, status, value, mo_string, translator_id, translation_date  
							FROM {$this->wpdb->prefix}icl_string_translations 
							WHERE string_id=%d {$extra_cond}
						",
						$row['string_id']
					),
					ARRAY_A
				);

				if ( $tr ) {
					foreach ( $tr as $t ) {
						$string_translations[ $row['string_id'] ]['translations'][ $t['language'] ] = $t;
					}
				}
			}
		}

		return $string_translations;
	}

	private function get_context() {
		if ( ! array_key_exists( 'context', $_GET ) ) {
			return null;
		}

		$context = stripslashes( html_entity_decode( (string) Sanitize::stringProp( 'context', $_GET ), ENT_QUOTES ) );

		if ( self::EMPTY_CONTEXT_LABEL === $context ) {
			return '';
		}

		$parts = explode( '-', $context );
		if ( count( $parts ) < 2 ) {
			return $context;
		}

		list( $kind_slug, $name ) = $parts;

		$excluded_package_condition = $this->get_excluded_string_package_condition( false );

		if ( '' === $excluded_package_condition ) {
			return $context;
		}

		$query  = $this->wpdb->prepare(
			"SELECT kind FROM {$this->wpdb->prefix}icl_string_packages sp WHERE kind_slug = %s AND name = %s {$excluded_package_condition} LIMIT 1",
			$kind_slug,
			$name
		);
		$result = $this->wpdb->get_row( $query );

		if ( $result ) {
			return null;
		}

		return $context;
	}

	private function get_value_search_query() {
		$language_where = wpml_collect(
			[
				$this->get_original_value_filter_sql(),
				$this->get_name_filter_sql(),
				$this->get_context_filter_sql(),

			]
		);

		$search_context = $this->get_search_context_filter();

		if ( $search_context['translation'] ) {
			$language_where->push( $this->get_translation_value_filter_sql() );
		}
		if ( $search_context['mo_string'] ) {
			$language_where->push( $this->get_mo_file_value_filter_sql() );
		}

		return sprintf( '((%s))', $language_where->implode( ') OR (' ) );
	}

	private function get_original_value_filter_sql() {
		return $this->get_column_filter_sql( 's.value', $this->get_search_filter(), $this->is_exact_match() );
	}

	private function get_name_filter_sql() {
		return $this->get_column_filter_sql( 's.name', $this->get_search_filter(), $this->is_exact_match() );
	}
	private function get_context_filter_sql() {
		return $this->get_column_filter_sql( 's.gettext_context', $this->get_search_filter(), $this->is_exact_match() );
	}

	private function get_translation_value_filter_sql() {
		return $this->get_column_filter_sql( 'str.value', $this->get_search_filter(), $this->is_exact_match() );
	}

	private function get_mo_file_value_filter_sql() {
		return $this->get_column_filter_sql( 'str.mo_string', $this->get_search_filter(), $this->is_exact_match() );
	}

	private function get_column_filter_sql( $column, $search_filter, $exact_match ) {
		$search_filter_html = esc_html( (string) $search_filter );

		$column             = esc_sql( $column );
		$search_filter      = esc_sql( (string) $search_filter );
		$search_filter_html = esc_sql( $search_filter_html );

		if ( $search_filter === $search_filter_html ) {
			return $exact_match
				? "$column = '$search_filter'"
				: "$column LIKE '%$search_filter%'";
		}

		return $exact_match
			? "($column = '$search_filter' OR $column = '$search_filter_html')"
			: "($column LIKE '%$search_filter%' OR $column LIKE '%$search_filter_html%')";
	}

	public function get_per_domain_counts( $status ) {
		$extra_cond = '';
		$extra_sql  = '';

		if ( false !== $status ) {
			$status = (int) $status;

			if ( ICL_TM_COMPLETE === $status ) {
				$extra_cond .= ' AND s.status = ' . ICL_TM_COMPLETE;
			} elseif ( ICL_STRING_TRANSLATION_STRING_TRACKING_TYPE_FRONTEND === $status ) {
				$extra_sql  .= " INNER JOIN {$this->wpdb->prefix}icl_string_positions string_positions ON s.id = string_positions.string_id";
				$extra_cond .= ' AND string_positions.kind = ' . ICL_STRING_TRANSLATION_STRING_TRACKING_TYPE_FRONTEND;
				$extra_cond .= ' AND s.status IN (' . ICL_STRING_TRANSLATION_PARTIAL . ',' . ICL_TM_NEEDS_UPDATE . ',' . ICL_TM_NOT_TRANSLATED . ')';
			} else {
				$extra_cond .= ' AND s.status IN (' . ICL_STRING_TRANSLATION_PARTIAL . ',' . ICL_TM_NEEDS_UPDATE . ',' . ICL_TM_NOT_TRANSLATED . ')';
			}
		}

		$excluded_package_condition = $this->get_excluded_string_package_condition();

		$join_clause = '';
		if ( '' !== $excluded_package_condition ) {
			$join_clause = "LEFT JOIN {$this->wpdb->prefix}icl_string_packages sp ON s.string_package_id = sp.ID";
		}

		$query = "
			SELECT context, COUNT(context) AS c
			FROM {$this->wpdb->prefix}icl_strings s
			{$join_clause}
			{$extra_sql}
			WHERE 1 {$extra_cond} {$this->filter_empty_value()}
			{$excluded_package_condition}
			GROUP BY context {$this->getCollateForContextColumn( $this->wpdb )}
			ORDER BY context ASC
		";

		return $this->wpdb->get_results( $query );
	}

	private function get_excluded_string_package_condition( $is_exclusive = true ) {
		$user_meta     = get_user_meta( get_current_user_id(), 'context_page_builder_hide_options', true );
		$user_meta     = $user_meta ? $user_meta : [];
		$page_builders = (array) apply_filters( 'wpml_get_page_builder_text_domains', [] );
		$not_selected  = [];

		foreach ( $page_builders as $page_builder ) {
			if ( isset( $user_meta[ $page_builder ] ) && 'on' === $user_meta[ $page_builder ] ) {
				continue;
			}
			$not_selected[] = $page_builder;
		}

		if ( empty( $not_selected ) ) {
			return '';
		}

		$not_sql             = $is_exclusive ? 'NOT' : '';
		$check_sp_id_text    = $is_exclusive ? '  OR s.string_package_id IS NULL ' : '';
		$not_selected_string = wpml_prepare_in( $not_selected, '%s' );
		$sql                 = " AND (sp.kind {$not_sql} IN ( {$not_selected_string} ) {$check_sp_id_text}) ";

		return $sql;
	}

	private function get_strings_per_page() {
		$st_settings = $this->sitepress->get_setting( 'st' );

		return isset( $st_settings['strings_per_page'] ) ? $st_settings['strings_per_page'] : WPML_ST_DEFAULT_STRINGS_PER_PAGE;
	}

	private function get_results( $where_snippet, $extra_cond, $offset, $limit, $joins = array(), $selects = array() ) {
		$query  = $this->build_sql_start( $selects, $joins );
		$query .= $where_snippet;
		$query .= " {$extra_cond} ";
		$query .= $this->filter_empty_value();
		$query .= $this->order_limits( $offset, $limit );

		$query_count  = $this->build_sql_count_start( $joins );
		$query_count .= $where_snippet;
		$query_count .= " {$extra_cond} ";
		$query_count .= $this->filter_empty_value();

		$num_rows = $this->wpdb->get_var( $query_count );

		$res = $this->wpdb->get_results( $query, ARRAY_A );
		$this->set_pagination_counts( $limit, $num_rows );

		return $res;
	}

	private function filter_empty_value() {
		return $this->has_text_is_usable()
			? ' AND s.has_text = 1'
			: " AND TRIM(s.value) <> ''";
	}

	private function has_text_is_usable() {
		if ( null === $this->has_text_usable ) {
			$this->has_text_usable = StringValue::isReady();
		}

		return $this->has_text_usable;
	}

	private function order_limits( $offset, $limit ) {
		return '  ORDER BY string_id DESC LIMIT ' . (int) $offset . ',' . (int) $limit;
	}

	private function set_pagination_counts( $limit, $num_rows ) {
		if ( ! is_null( $this->wp_query ) ) {
			$this->wp_query->found_posts                  = $num_rows;
			$this->wp_query->query_vars['posts_per_page'] = $limit;
			$this->wp_query->max_num_pages                = ceil( $this->wp_query->found_posts / $limit );
		}
	}

	private function build_sql_start( $selects = array(), $joins = array() ) {
		array_unshift( $selects, 'DISTINCT s.id AS string_id, s.language AS string_language, s.string_package_id, s.context, s.gettext_context, s.name, s.value, s.status AS status, s.translation_priority' );

		return 'SELECT ' . implode( ', ', $selects ) . " FROM {$this->wpdb->prefix}icl_strings s " . implode( PHP_EOL, $joins ) . ' ';
	}

	private function build_sql_count_start( $joins = array() ) {
		return "SELECT COUNT(DISTINCT s.id) FROM {$this->wpdb->prefix}icl_strings s " . implode( PHP_EOL, $joins ) . ' ';
	}

	private function get_search_filter() {
		if ( array_key_exists( 'search', $_GET ) ) {
			return stripcslashes( $_GET['search'] );
		}

		return false;
	}

	private function is_exact_match() {
		if ( array_key_exists( 'em', $_GET ) ) {
			return 1 === (int) $_GET['em'];
		}

		return false;
	}

	private function get_search_context_filter() {
		$result = array(
			'original'    => true,
			'translation' => false,
			'mo_string'   => false,
		);
		if ( array_key_exists( 'search_translation', $_GET ) && ! $this->must_show_all_results() ) {
			$result['translation'] = (bool) $_GET['search_translation'];
			$result['mo_string']   = (bool) $_GET['search_translation'];
		}

		return $result;
	}

	private function is_troubleshooting_filter_enabled() {
		return (bool) filter_input( INPUT_GET, 'troubleshooting', FILTER_VALIDATE_BOOLEAN );
	}

	private function must_show_all_results() {
		return isset( $_GET['show_results'] ) && 'all' === $_GET['show_results'];
	}

}
