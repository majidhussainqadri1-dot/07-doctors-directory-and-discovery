<?php
defined( 'ABSPATH' ) || exit;

final class DDD_Repository {
	use DDD_Repository_Trait_1, DDD_Repository_Trait_2, DDD_Repository_Trait_3, DDD_Repository_Trait_4, DDD_Repository_Trait_5, DDD_Repository_Trait_6, DDD_Repository_Trait_7, DDD_Repository_Trait_8;

	const DEFAULT_LIMIT = 24;
	const MAX_LIMIT = 60;
	const RECONCILE_BATCH = 100;






















}

final class DDD_Directory {
	use DDD_Directory_Trait_1, DDD_Directory_Trait_2, DDD_Directory_Trait_3;










}

if ( ! class_exists( 'SDD_Directory' ) ) {
	class_alias( 'DDD_Directory', 'SDD_Directory' );
}
