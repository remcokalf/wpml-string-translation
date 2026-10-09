<?php

namespace WP_CLI\Bootstrap;

use WP_CLI;
use WP_CLI\Utils;

final class LoadRequiredCommand implements BootstrapStep {

	public function process( BootstrapState $state ) {
		if ( $state->getValue( BootstrapState::IS_PROTECTED_COMMAND, false ) ) {
			return $state;
		}

		$runner = new RunnerInstance();
		if ( ! isset( $runner()->config['require'] ) ) {
			return $state;
		}

		foreach ( $runner()->config['require'] as $path ) {
			if ( ! file_exists( $path ) ) {
				$context        = '';
				$required_files = $runner()->get_required_files();
				foreach ( [ 'global', 'project', 'runtime' ] as $scope ) {
					if ( in_array( $path, $required_files[ $scope ], true ) ) {
						switch ( $scope ) {
							case 'global':
								$context = ' (from global ' . Utils\basename( $runner()->get_global_config_path() ) . ')';
								break;
							case 'project':
								$context = ' (from project\'s ' . Utils\basename( $runner()->get_project_config_path() ) . ')';
								break;
							case 'runtime':
								$context = ' (from runtime argument)';
								break;
						}
						break;
					}
				}
				WP_CLI::error( sprintf( "Required file '%s' doesn't exist%s.", Utils\basename( $path ), $context ) );
			}
			Utils\load_file( $path );
			WP_CLI::debug( 'Required file from config: ' . $path, 'bootstrap' );
		}

		return $state;
	}
}
