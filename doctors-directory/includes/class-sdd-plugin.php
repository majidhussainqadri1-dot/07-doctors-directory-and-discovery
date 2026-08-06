<?php
defined( 'ABSPATH' ) || exit;

final class DDD_Observability {
	use DDD_Observability_Trait_1;






}

final class DDD_REST {
	use DDD_REST_Trait_1, DDD_REST_Trait_2;

	const NS = 'doctors-directory-discovery/v1';












}

final class DDD_Plugin {
	use DDD_Plugin_Trait_1;












}

if ( ! class_exists( 'SDD_Plugin' ) ) {
	class_alias( 'DDD_Plugin', 'SDD_Plugin' );
}
