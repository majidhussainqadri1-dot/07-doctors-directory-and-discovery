<?php
defined( 'ABSPATH' ) || exit;

final class DDD_Profile {
	use DDD_Profile_Trait_1, DDD_Profile_Trait_2;







}

if ( ! class_exists( 'SDD_Profile' ) ) {
	class_alias( 'DDD_Profile', 'SDD_Profile' );
}
