<?php
defined( 'ABSPATH' ) || exit;

final class DDD_Activator {
	use DDD_Activator_Trait_1, DDD_Activator_Trait_2, DDD_Activator_Trait_3;

	const LOCK_OPTION = 'ddd_activation_lock';
	const PAGE_MAP_OPTION = 'ddd_page_map';












}

if ( ! class_exists( 'SDD_Activator' ) ) {
	class_alias( 'DDD_Activator', 'SDD_Activator' );
}
