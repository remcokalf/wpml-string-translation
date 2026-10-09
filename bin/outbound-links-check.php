<?php

const ALLOWLIST_REGEX = '#(^|.*/)('
	. 'bin/outbound-links-check\.php'
	. '|classes/OutboundLinks/'
	. '|\.gitlab-ci/quality/outbound-links\.modified-lines\.yml'
	. '|docs/OUTBOUND-LINKS\.md'
	. '|CLAUDE\.md|README\.md|changelog\.md|readme\.txt'
	. '|plugin\.php'
	. '|inc/gettext/wpml-po-parser\.class\.php'
	. '|tests/'
	. '|.*__tests__/'
	. '|.*\.test\.(?:js|jsx|ts|tsx)$'
	. ')#';

const MACHINE_HOST_REGEX = '#(api|ate|ams|cdn|tp|tp-staging|health|trans)\.wpml\.org#i';

const LINK_REGEX = '#https?://[a-z0-9.\-]*wpml\.org#i';

function resolve_base_ref( $arg ) {
	if ( is_string( $arg ) && '' !== $arg ) {
		return $arg;
	}

	foreach ( [ 'origin/develop', 'develop' ] as $ref ) {
		exec( 'git rev-parse --verify --quiet ' . escapeshellarg( $ref ), $out, $code );
		if ( 0 === $code ) {
			$base = trim( (string) shell_exec( 'git merge-base HEAD ' . escapeshellarg( $ref ) ) );
			if ( '' !== $base ) {
				return $base;
			}
		}
	}

	return 'HEAD~1';
}

$base_ref = resolve_base_ref( isset( $argv[1] ) ? $argv[1] : null );

$diff = (string) shell_exec(
	'git diff --unified=0 ' . escapeshellarg( $base_ref . '...HEAD' ) . ' 2>' . ( DIRECTORY_SEPARATOR === '\\' ? 'NUL' : '/dev/null' )
);

$current_file = '';
$findings     = [];

foreach ( explode( "\n", $diff ) as $line ) {
	if ( 0 === strpos( $line, '+++ ' ) ) {
		$current_file = preg_replace( '#^\+\+\+ (b/)?#', '', $line );
		continue;
	}

	if ( '' === $line || '+' !== $line[0] || 0 === strpos( $line, '+++' ) ) {
		continue;
	}

	$added = substr( $line, 1 );

	if ( ! preg_match( LINK_REGEX, $added ) ) {
		continue;
	}

	if ( preg_match( ALLOWLIST_REGEX, $current_file ) ) {
		continue;
	}

	if ( preg_match( '#OutboundLinks|outboundLink#', $added ) ) {
		continue;
	}

	if ( preg_match( '#^[\'"]https?://#', trim( $added ) ) ) {
		continue;
	}

	if ( preg_match( MACHINE_HOST_REGEX, $added ) ) {
		$remainder = preg_replace( '#https?://' . substr( MACHINE_HOST_REGEX, 1, -3 ) . '[^"\'\s)]*#i', '', $added );
		if ( ! preg_match( LINK_REGEX, (string) $remainder ) ) {
			continue;
		}
	}

	$findings[] = [ $current_file, trim( $added ) ];
}

echo "\n";

if ( ! empty( $findings ) ) {
	foreach ( $findings as $f ) {
		echo '  ' . $f[0] . "\n      " . $f[1] . "\n";
	}
	echo "\n";
	echo count( $findings ) . " raw wpml.org link(s) added outside the OutboundLinks helper.\n";
	echo "Route user-facing wpml.org links through the ST OutboundLinks wrapper. See the core plugin's docs/OUTBOUND-LINKS.md.\n";
	exit( 1 );
}

echo "outbound-links: no raw wpml.org links added outside the helper.\n";
exit( 0 );
