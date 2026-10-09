<?php


namespace WPML\ST\Upgrade\Command;


use WPML\Element\API\Languages;
use WPML\FP\Cast;
use WPML\FP\Fns;
use WPML\FP\Logic;
use WPML\FP\Lst;
use WPML\FP\Obj;
use WPML\FP\Relation;
use WPML\FP\Str;
use WPML\LIB\WP\Option;
use WPML\ST\MO\File\ManagerFactory;
use function WPML\FP\partial;
use function WPML\FP\pipe;

class MigrateMultilingualWidgets implements \IWPML_St_Upgrade_Command {

	const RENAME_BATCH = 500;

	public function run() {
		$multiLingualWidgets = Option::getOr( 'widget_text_icl', [] );
		$multiLingualWidgets = array_filter( $multiLingualWidgets, Logic::complement( 'is_scalar' ) );
		if ( ! $multiLingualWidgets ) {
			return true;
		}

		$textWidgets = Option::getOr( 'widget_text', [] );
		if ( $textWidgets ) {
			$textWidgetsKeys = Obj::keys( $textWidgets );
			$theHighestTextWidgetId = max( $textWidgetsKeys );
		} else {
			$theHighestTextWidgetId      = 0;
			$textWidgets['_multiwidget'] = 1;
		}

		$transformWidget = pipe(
			Obj::renameProp( 'icl_language', 'wpml_language' ),
			Obj::over( Obj::lensProp( 'wpml_language' ), Logic::ifElse( Relation::equals( 'multilingual' ), Fns::always( 'all' ), Fns::identity() ) )
		);

		$oldToNewIdMap = [];
		foreach ( $multiLingualWidgets as $id => $widget ) {
			$newId                = ++ $theHighestTextWidgetId;
			$oldToNewIdMap[ $id ] = $newId;

			$textWidgets = Obj::assoc( $newId, $transformWidget( $widget ), $textWidgets );
		}

		Option::update( 'widget_text', $textWidgets );
		Option::delete( 'widget_text_icl' );

		$sidebars = wp_get_sidebars_widgets();
		$sidebars = $this->convertSidebarsConfig( $sidebars, $oldToNewIdMap );
		wp_set_sidebars_widgets( $sidebars );

		$this->convertWidgetsContentStrings();

		return true;
	}

	private function convertSidebarsConfig( $sidebars, array $oldToNewIdMap ) {
		$isMultilingualWidget = Str::startsWith( 'text_icl' );
		$extractIdNumber      = pipe( Str::split( '-' ), Lst::last(), Cast::toInt() );

		$mapWidgetId = Logic::ifElse(
			$isMultilingualWidget,
			pipe( $extractIdNumber, Obj::prop( Fns::__, $oldToNewIdMap ), Str::concat( 'text-' ) ),
			Fns::identity()
		);

		return Fns::map( Fns::map( $mapWidgetId ), $sidebars );
	}

	private function convertWidgetsContentStrings() {
		$this->renameWidgetBodyStrings();

		$locales = Fns::map( Languages::getWPLocale(), Languages::getSecondaries() );
		Fns::map( partial( [ ManagerFactory::create(), 'add' ], 'Widgets' ), $locales );
	}

	private function renameWidgetBodyStrings() {
		global $wpdb;

		$table  = $wpdb->prefix . 'icl_strings';
		$like   = $wpdb->esc_like( 'widget body - text_icl' ) . '%';
		$lastId = 0;

		do {
			$rows = (array) $wpdb->get_results(
				$wpdb->prepare(
					"SELECT `id`, `value` FROM `{$table}` WHERE `name` LIKE %s AND `id` > %d ORDER BY `id` ASC LIMIT %d",
					$like,
					$lastId,
					self::RENAME_BATCH
				),
				ARRAY_A
			);
			$read = count( $rows );

			foreach ( $rows as $row ) {
				$id     = (int) $row['id'];
				$lastId = max( $lastId, $id );

				$wpdb->query(
					$wpdb->prepare(
						"UPDATE `{$table}` SET `name` = %s WHERE `id` = %d",
						'widget body - ' . md5( (string) $row['value'] ),
						$id
					)
				);
			}
		} while ( $read === self::RENAME_BATCH );
	}

	public function run_ajax() {
	}

	public function run_frontend() {
	}

	public static function get_command_id() {
		return __CLASS__;
	}


}