<?php

namespace WPML\ST\Batch\Translation;

use WPML\Collect\Support\Traits\Macroable;
use WPML\FP\Fns;
use WPML\FP\Logic;
use WPML\FP\Obj;
use WPML\FP\Str;
use WPML\FP\Wrapper;
use WPML\Setup\Option;
use WPML\ST\API\Fns as ST_API;
use function WPML\Container\make;
use function WPML\FP\curryN;
use function WPML\FP\invoke;
use function WPML\FP\pipe;
use function WPML\FP\spreadArgs;

class StringTranslations {

	use Macroable;

	public static function init() {

		self::macro( 'isTranslated', Obj::prop( 'field_translate' ) );

		self::macro(
			'isBatchField',
			curryN(
				1,
				function( $field ) {
					return self::isBatchId( Obj::prop( 'field_type', $field ) );
				}
			)
		);

		self::macro(
			'save',
			curryN(
				3,
				function ( $element_type_prefix, $job, callable $decoder ) {
					if ( 'st-batch' === $element_type_prefix ) {

						$decodeField = pipe(
							Obj::props( [ 'field_data_translated', 'field_format' ] ),
							spreadArgs( $decoder )
						);

						$getStringId = pipe( Obj::prop( 'field_type' ), self::decodeStringId() );

						$saveTranslation = Fns::converge(
							ST_API::saveTranslation( Fns::__, $job->language_code, Fns::__, ICL_TM_COMPLETE ),
							[ $getStringId, $decodeField ]
						);

						$filterTranslatedAndBatchField = Logic::allPass( [ self::isTranslated(), self::isBatchField() ] );

						Wrapper::of( $job->elements )
							   ->map( Fns::filter( $filterTranslatedAndBatchField ) )
							   ->map( Fns::each( $saveTranslation ) );
					}
				}
			)
		);

		self::macro(
			'cancelTranslations',
			curryN(
				1,
				function ( $job ) {
					if ( $job instanceof \WPML_TM_Post_Job_Entity && $job->get_type() === 'st-batch_strings' ) {
						$language = $job->get_target_language();

						$getTranslations = function ( $stringId ) use ( $language ) {
							return [
								'string_id'   => $stringId,
								'translation' => Obj::pathOr( '', [ $language, 'value' ], ST_API::getTranslations( $stringId ) ),
							];
						};

						$cancelStatus = Logic::ifElse( Obj::prop( 'translation' ), Fns::always( ICL_TM_COMPLETE ), Fns::always( ICL_TM_NOT_TRANSLATED ) );

						$cancel = Fns::converge(
							ST_API::updateStatus( Fns::__, $language, Fns::__ ),
							[ Obj::prop( 'string_id' ), $cancelStatus ]
						);

						\wpml_collect( $job->get_elements() )
							->map( invoke( 'get_type' ) )
							->filter( Fns::unary( self::isBatchId() ) )
							->map( self::decodeStringId() )
							->map( $getTranslations )
							->map( Fns::tap( $cancel ) );
					}
				}
			)
		);

		self::macro(
			'addExisting',
			curryN(
				3,
				function ( $prevTranslations, $package, $lang ) {

					$getTranslation = curryN(
						3,
						function ( $lang, $data, $stringId ) {
							if ( 1 === $data['translate'] && self::isBatchId( $stringId ) ) {
								$translation = ST_API::getTranslation( self::decodeStringId( $stringId ), $lang );
								return (object) [
									'id'          => $stringId,
									'translation' => base64_encode( is_null( $translation ) ? '' : $translation ),
								];
							}

							return null;
						}
					);

					$createField = function ( $translation ) {
						return make( 'WPML_TM_Translated_Field', [ '', '', $translation, false ] );
					};

					$updatePrevious = function ( $prev, $string ) {
						$prev[ $string->id ] = $string->translation;

						return $prev;
					};

					$hasTranslation = Obj::prop( 'translation' );

					return Wrapper::of( $package['contents'] )
							  ->map( Fns::map( $getTranslation( $lang ) ) )
							  ->map( Fns::filter( $hasTranslation ) )
							  ->map( Fns::map( Obj::evolve( [ 'translation' => $createField ] ) ) )
							  ->map( Fns::reduce( $updatePrevious, $prevTranslations ) )
							  ->get();
				}
			)
		);

		self::macro(
			'markTranslationsAsInProgress',
			curryN(
				3,
				function ( $getJobStatus, $element, $post ) {
					if ( $element instanceof \WPML_TM_Translation_Batch_Element && $element->get_element_type() === 'st-batch' ) {
						$statuses = \wpml_collect( $getJobStatus( $post->post_id ) );

						$addTranslationWithStatus = function ( $stringId, $targetLanguage ) use ( $statuses ) {
							$status = $statuses->get( $targetLanguage );

							if ( null === $status || '' === $status ) {
								return;
							}

							ST_API::updateStatus( $stringId, $targetLanguage, (int) $status );
						};

						\wpml_collect( $post->string_data )
						->keys()
						->map( Fns::unary( StringTranslations::decodeStringId() ) )
						->map( Fns::unary( 'intval' ) )
						->crossJoin( array_keys( $element->get_target_langs() ) )
						->map( Fns::tap( spreadArgs( $addTranslationWithStatus ) ) );
					}
				}
			)
		);
	}

	public static function updateStatus( $element_type_prefix = null, $job = null ) {
		return call_user_func_array(
			curryN(
				2,
				function ( $element_type_prefix, $job ) {
					if ( 'st-batch' === $element_type_prefix ) {
						$getStringId = pipe( Obj::prop( 'field_type' ), self::decodeStringId() );

						$updateStatus = ST_API::updateStatus( Fns::__, $job->language_code, ICL_TM_IN_PROGRESS );

						\wpml_collect( $job->elements )
							->filter( self::isBatchField() )
							->map( $getStringId )
							->each( $updateStatus );
					}
				}
			),
			func_get_args()
		);
	}

	public static function decodeStringId( $str = null ) {
		return call_user_func_array(
			curryN(
				1,
				function( $str ) {
					return Str::replace( Module::STRING_ID_PREFIX, '', $str );
				}
			),
			func_get_args()
		);
	}

	public static function isBatchId( $str = null ) {
		return call_user_func_array(
			curryN(
				1,
				function( $str ) {
					return Str::startsWith( Module::STRING_ID_PREFIX, $str );
				}
			),
			func_get_args()
		);
	}

	public static function isBatchField( $field = null ) {
		return call_user_func_array(
			curryN(
				1,
				function( $field ) {
					return self::isBatchId( Obj::prop( 'field_type', $field ) );
				}
			),
			func_get_args()
		);
	}

}

StringTranslations::init();
