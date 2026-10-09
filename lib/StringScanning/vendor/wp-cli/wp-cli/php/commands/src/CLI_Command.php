<?php

use Composer\Semver\Comparator;
use WP_CLI\Completions;
use WP_CLI\Formatter;
use WP_CLI\Process;
use WP_CLI\Utils;

class CLI_Command extends WP_CLI_Command {

	private function command_to_array( $command ) {
		$dump = [
			'name'        => $command->get_name(),
			'description' => $command->get_shortdesc(),
			'longdesc'    => $command->get_longdesc(),
			'hook'        => $command->get_hook(),
		];

		foreach ( $command->get_subcommands() as $subcommand ) {
			$dump['subcommands'][] = $this->command_to_array( $subcommand );
		}

		if ( empty( $dump['subcommands'] ) ) {
			$dump['synopsis'] = (string) $command->get_synopsis();
		}

		return $dump;
	}

	public function version() {
		WP_CLI::line( 'WP-CLI ' . WP_CLI_VERSION );
	}

	public function info( $_, $assoc_args ) {
		$system_os = PHP_MAJOR_VERSION < 7
			? php_uname()
			: sprintf(
				'%s %s %s %s',
				php_uname( 's' ),
				php_uname( 'r' ),
				php_uname( 'v' ),
				php_uname( 'm' )
			);

		$shell = getenv( 'SHELL' );
		if ( ! $shell && Utils\is_windows() ) {
			$shell = getenv( 'ComSpec' );
		}

		$php_bin = Utils\get_php_binary();

		$runner = WP_CLI::get_runner();

		$packages_dir = $runner->get_packages_dir_path();
		if ( ! is_dir( $packages_dir ) ) {
			$packages_dir = null;
		}

		if ( Utils\get_flag_value( $assoc_args, 'format' ) === 'json' ) {
			$info = [
				'system_os'                => $system_os,
				'shell'                    => $shell,
				'php_binary_path'          => $php_bin,
				'php_version'              => PHP_VERSION,
				'php_ini_used'             => get_cfg_var( 'cfg_file_path' ),
				'mysql_binary_path'        => Utils\get_mysql_binary_path(),
				'mysql_version'            => Utils\get_mysql_version(),
				'sql_modes'                => Utils\get_sql_modes(),
				'wp_cli_dir_path'          => WP_CLI_ROOT,
				'wp_cli_vendor_path'       => WP_CLI_VENDOR_DIR,
				'wp_cli_phar_path'         => defined( 'WP_CLI_PHAR_PATH' ) ? WP_CLI_PHAR_PATH : '',
				'wp_cli_packages_dir_path' => $packages_dir,
				'wp_cli_cache_dir_path'    => Utils\get_cache_dir(),
				'global_config_path'       => $runner->global_config_path,
				'project_config_path'      => $runner->project_config_path,
				'wp_cli_version'           => WP_CLI_VERSION,
			];

			WP_CLI::line( json_encode( $info ) );
		} else {
			WP_CLI::line( "OS:\t" . $system_os );
			WP_CLI::line( "Shell:\t" . $shell );
			WP_CLI::line( "PHP binary:\t" . $php_bin );
			WP_CLI::line( "PHP version:\t" . PHP_VERSION );
			WP_CLI::line( "php.ini used:\t" . get_cfg_var( 'cfg_file_path' ) );
			WP_CLI::line( "MySQL binary:\t" . Utils\get_mysql_binary_path() );
			WP_CLI::line( "MySQL version:\t" . Utils\get_mysql_version() );
			WP_CLI::line( "SQL modes:\t" . implode( ',', Utils\get_sql_modes() ) );
			WP_CLI::line( "WP-CLI root dir:\t" . WP_CLI_ROOT );
			WP_CLI::line( "WP-CLI vendor dir:\t" . WP_CLI_VENDOR_DIR );
			WP_CLI::line( "WP_CLI phar path:\t" . ( defined( 'WP_CLI_PHAR_PATH' ) ? WP_CLI_PHAR_PATH : '' ) );
			WP_CLI::line( "WP-CLI packages dir:\t" . $packages_dir );
			WP_CLI::line( "WP-CLI cache dir:\t" . Utils\get_cache_dir() );
			WP_CLI::line( "WP-CLI global config:\t" . $runner->global_config_path );
			WP_CLI::line( "WP-CLI project config:\t" . $runner->project_config_path );
			WP_CLI::line( "WP-CLI version:\t" . WP_CLI_VERSION );
		}
	}

	public function check_update( $_, $assoc_args ) {
		$updates = $this->get_updates( $assoc_args );

		if ( $updates ) {
			$formatter = new Formatter(
				$assoc_args,
				[ 'version', 'update_type', 'package_url', 'status', 'requires_php' ]
			);
			$formatter->display_items( $updates );
		} elseif ( empty( $assoc_args['format'] ) || 'table' === $assoc_args['format'] ) {
			$update_type = $this->get_update_type_str( $assoc_args );
			WP_CLI::success( "WP-CLI is at the latest{$update_type}version." );
		}
	}

	public function update( $_, $assoc_args ) {
		if ( ! Utils\inside_phar() ) {
			WP_CLI::error( 'You can only self-update Phar files.' );
		}

		$old_phar = realpath( $_SERVER['argv'][0] );

		if ( ! is_writable( $old_phar ) ) {
			WP_CLI::error( sprintf( '%s is not writable by current user.', $old_phar ) );
		} elseif ( ! is_writable( dirname( $old_phar ) ) ) {
			WP_CLI::error( sprintf( '%s is not writable by current user.', dirname( $old_phar ) ) );
		}

		if ( Utils\get_flag_value( $assoc_args, 'nightly' ) ) {
			WP_CLI::confirm( sprintf( 'You are currently using WP-CLI version %s. Would you like to update to the latest nightly version?', WP_CLI_VERSION ), $assoc_args );
			$download_url = 'https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli-nightly.phar';
			$md5_url      = 'https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli-nightly.phar.md5';
			$sha512_url   = 'https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli-nightly.phar.sha512';
		} elseif ( Utils\get_flag_value( $assoc_args, 'stable' ) ) {
			WP_CLI::confirm( sprintf( 'You are currently using WP-CLI version %s. Would you like to update to the latest stable release?', WP_CLI_VERSION ), $assoc_args );
			$download_url = 'https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar';
			$md5_url      = 'https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar.md5';
			$sha512_url   = 'https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar.sha512';
		} else {

			$updates = $this->get_updates( $assoc_args );

			$newest = $this->array_find(
				$updates,
				static function ( $update ) {
					return 'available' === $update['status'];
				}
			);

			if ( ! $newest ) {
				$update_type = $this->get_update_type_str( $assoc_args );
				WP_CLI::success( "WP-CLI is at the latest{$update_type}version." );
				return;
			}

			WP_CLI::confirm( sprintf( 'You have version %s. Would you like to update to %s?', WP_CLI_VERSION, $newest['version'] ), $assoc_args );

			$download_url = $newest['package_url'];
			$md5_url      = str_replace( '.phar', '.phar.md5', $download_url );
			$sha512_url   = str_replace( '.phar', '.phar.sha512', $download_url );
		}

		WP_CLI::log( sprintf( 'Downloading from %s...', $download_url ) );

		$temp = Utils\get_temp_dir() . uniqid( 'wp_', true ) . '.phar';

		$headers = [];
		$options = [
			'timeout'  => 600,
			'filename' => $temp,
			'insecure' => (bool) Utils\get_flag_value( $assoc_args, 'insecure', false ),
		];

		Utils\http_request( 'GET', $download_url, null, $headers, $options );

		unset( $options['filename'] );

		$this->validate_hashes( $temp, $sha512_url, $md5_url );

		$allow_root = WP_CLI::get_runner()->config['allow-root'] ? '--allow-root' : '';
		$php_binary = Utils\get_php_binary();
		$process    = Process::create( "{$php_binary} $temp --info {$allow_root}" );
		$result     = $process->run();
		if ( 0 !== $result->return_code || false === stripos( $result->stdout, 'WP-CLI version' ) ) {
			$multi_line = explode( PHP_EOL, $result->stderr );
			WP_CLI::error_multi_line( $multi_line );
			WP_CLI::error( 'The downloaded PHAR is broken, try running wp cli update again.' );
		}

		WP_CLI::log( 'New version works. Proceeding to replace.' );

		$mode = fileperms( $old_phar ) & 511;

		if ( false === chmod( $temp, $mode ) ) {
			WP_CLI::error( sprintf( 'Cannot chmod %s.', $temp ) );
		}

		class_exists( '\cli\Colors' );

		if ( false === rename( $temp, $old_phar ) ) {
			WP_CLI::error( sprintf( 'Cannot move %s to %s', $temp, $old_phar ) );
		}

		if ( Utils\get_flag_value( $assoc_args, 'nightly', false ) ) {
			$updated_version = 'the latest nightly release';
		} elseif ( Utils\get_flag_value( $assoc_args, 'stable', false ) ) {
			$updated_version = 'the latest stable release';
		} else {
			$updated_version = isset( $newest['version'] ) ? $newest['version'] : '<not provided>';
		}
		WP_CLI::success( sprintf( 'Updated WP-CLI to %s.', $updated_version ) );
	}

	private function validate_hashes( $file, $sha512_url, $md5_url ) {
		$algos = [
			'sha512' => $sha512_url,
			'md5'    => $md5_url,
		];

		foreach ( $algos as $algo => $url ) {
			$response = Utils\http_request( 'GET', $url );
			if ( '20' !== substr( $response->status_code, 0, 2 ) ) {
				WP_CLI::log( "Couldn't access $algo hash for release (HTTP code {$response->status_code})." );
				continue;
			}

			$file_hash = hash_file( $algo, $file );

			$release_hash = trim( $response->body );
			if ( $file_hash === $release_hash ) {
				WP_CLI::log( "$algo hash verified: $release_hash" );
				return;
			} else {
				WP_CLI::error( "$algo hash for download ($file_hash) is different than the release hash ($release_hash)." );
			}
		}

		WP_CLI::error( 'Release hash verification failed.' );
	}

	private function get_updates( $assoc_args ) {
		$url = 'https://api.github.com/repos/wp-cli/wp-cli/releases?per_page=100';

		$options = [
			'timeout'  => 30,
			'insecure' => (bool) Utils\get_flag_value( $assoc_args, 'insecure', false ),
		];

		$headers = [
			'Accept' => 'application/json',
		];

		$github_token = getenv( 'GITHUB_TOKEN' );
		if ( false !== $github_token ) {
			$headers['Authorization'] = 'token ' . $github_token;
		}

		$response = Utils\http_request( 'GET', $url, null, $headers, $options );

		if ( ! $response->success || 200 !== $response->status_code ) {
			WP_CLI::error( sprintf( 'Failed to get latest version (HTTP code %d).', $response->status_code ) );
		}

		$release_data = json_decode( $response->body );

		$updates = [
			'major' => false,
			'minor' => false,
			'patch' => false,
		];

		$updates_unavailable = [];

		foreach ( $release_data as $release ) {

			$release_version = $release->tag_name;
			if ( 'v' === substr( $release_version, 0, 1 ) ) {
				$release_version = ltrim( $release_version, 'v' );
			}

			$update_type = Utils\get_named_sem_ver( $release_version, WP_CLI_VERSION );

			if ( ! $update_type ) {
				continue;
			}

			if ( ! empty( $updates[ $update_type ] ) && ! Comparator::greaterThan( $release_version, $updates[ $update_type ]['version'] ) ) {
				continue;
			}

			$package_url   = null;
			$manifest_data = null;

			foreach ( $release->assets as $asset ) {
				if ( ! isset( $asset->browser_download_url ) ) {
					continue;
				}

				if ( substr( $asset->browser_download_url, - strlen( '.phar' ) ) === '.phar' ) {
					$package_url = $asset->browser_download_url;
				}

				if ( substr( $asset->browser_download_url, - strlen( 'manifest.json' ) ) === 'manifest.json' ) {
					$response = Utils\http_request( 'GET', $asset->browser_download_url, null, $headers, $options );

					if ( $response->success ) {
						$manifest_data = json_decode( $response->body );
					}
				}
			}

			if ( ! $package_url ) {
				continue;
			}

			if (
				isset( $manifest_data->requires_php ) &&
				! Comparator::greaterThanOrEqualTo( PHP_VERSION, $manifest_data->requires_php )
			) {
				$updates_unavailable[] = [
					'version'      => $release_version,
					'update_type'  => $update_type,
					'package_url'  => $release->assets[0]->browser_download_url,
					'status'       => 'unavailable',
					'requires_php' => $manifest_data->requires_php,
				];
			} else {
				$updates[ $update_type ] = [
					'version'      => $release_version,
					'update_type'  => $update_type,
					'package_url'  => $release->assets[0]->browser_download_url,
					'status'       => 'available',
					'requires_php' => isset( $manifest_data->requires_php ) ? $manifest_data->requires_php : '',
				];
			}
		}

		foreach ( $updates as $type => $value ) {
			if ( empty( $value ) ) {
				unset( $updates[ $type ] );
			}
		}

		foreach ( [ 'major', 'minor', 'patch' ] as $type ) {
			if ( true === Utils\get_flag_value( $assoc_args, $type ) ) {
				return ! empty( $updates[ $type ] ) ? [ $updates[ $type ] ] : false;
			}
		}

		if ( empty( $updates ) && preg_match( '#-alpha-(.+)$#', WP_CLI_VERSION, $matches ) ) {
			$version_url = 'https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/NIGHTLY_VERSION';
			$response    = Utils\http_request( 'GET', $version_url, null, [], $options );
			if ( ! $response->success || 200 !== $response->status_code ) {
				WP_CLI::error( sprintf( 'Failed to get current nightly version (HTTP code %d)', $response->status_code ) );
			}
			$nightly_version = trim( $response->body );

			if ( WP_CLI_VERSION !== $nightly_version ) {
				$manifest_data = null;

				$response = Utils\http_request( 'GET', 'https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli-nightly.manifest.json', null, $headers, $options );

				if ( $response->success ) {
					$manifest_data = json_decode( $response->body );
				}

				if (
					isset( $manifest_data->requires_php ) &&
					! Comparator::greaterThanOrEqualTo( PHP_VERSION, $manifest_data->requires_php )
				) {
					$updates_unavailable[] = [
						'version'      => $nightly_version,
						'update_type'  => 'nightly',
						'package_url'  => 'https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli-nightly.phar',
						'status'       => 'unvailable',
						'requires_php' => $manifest_data->requires_php,
					];
				} else {
					$updates['nightly'] = [
						'version'      => $nightly_version,
						'update_type'  => 'nightly',
						'package_url'  => 'https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli-nightly.phar',
						'status'       => 'available',
						'requires_php' => isset( $manifest_data->requires_php ) ? $manifest_data->requires_php : '',
					];
				}
			}
		}

		return array_merge( $updates_unavailable, array_values( $updates ) );
	}

	private function array_find( $arr, $callback ) {
		if ( function_exists( '\array_find' ) ) {
			return \array_find( $arr, $callback );
		}

		foreach ( $arr as $key => $value ) {
			if ( $callback( $value, $key ) ) {
				return $value;
			}
		}

		return null;
	}

	public function param_dump( $_, $assoc_args ) {
		$spec = WP_CLI::get_configurator()->get_spec();

		if ( Utils\get_flag_value( $assoc_args, 'with-values' ) ) {
			$config = WP_CLI::get_configurator()->to_array();
			foreach ( $spec as $key => $value ) {
				$current = null;
				if ( isset( $config[0][ $key ] ) ) {
					$current = $config[0][ $key ];
				}
				$spec[ $key ]['current'] = $current;
			}
		}

		if ( 'var_export' === Utils\get_flag_value( $assoc_args, 'format' ) ) {
			var_export( $spec );
		} else {
			echo json_encode( $spec );
		}
	}

	public function cmd_dump() {
		echo json_encode( $this->command_to_array( WP_CLI::get_root_command() ) );
	}

	public function completions( $_, $assoc_args ) {
		$line  = substr( $assoc_args['line'], 0, $assoc_args['point'] );
		$compl = new Completions( $line );
		$compl->render();
	}

	private function get_update_type_str( $assoc_args ) {
		$update_type = ' ';
		foreach ( [ 'major', 'minor', 'patch' ] as $type ) {
			if ( true === Utils\get_flag_value( $assoc_args, $type ) ) {
				$update_type = ' ' . $type . ' ';
				break;
			}
		}
		return $update_type;
	}

	public function has_command( $_, $assoc_args ) {

		$command = explode( ' ', implode( ' ', $_ ) );

		WP_CLI::halt( is_array( WP_CLI::get_runner()->find_command_to_run( $command ) ) ? 0 : 1 );
	}
}
