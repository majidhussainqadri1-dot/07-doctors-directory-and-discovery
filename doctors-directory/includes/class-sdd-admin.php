<?php
defined( 'ABSPATH' ) || exit;

final class DDD_Admin {
	use DDD_Admin_Trait_1, DDD_Admin_Trait_2, DDD_Admin_Trait_3, DDD_Admin_Trait_4;

	const PER_PAGE = 50;














}

if ( ! class_exists( 'SDD_Admin' ) ) {
	class_alias( 'DDD_Admin', 'SDD_Admin' );
}
