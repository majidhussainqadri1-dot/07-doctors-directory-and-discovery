<?php
defined( 'ABSPATH' ) || exit;

/**
 * Versioned cross-module contracts. File 07 owns only its projection and directory state.
 */
final class DDD_Contracts {
	use DDD_Contracts_Trait_1, DDD_Contracts_Trait_2, DDD_Contracts_Trait_3;

	const IDENTITY_FILTER = 'ddd_contract_identity_claims_v1';
	const VERIFICATION_FILTER = 'ddd_contract_verification_claims_v1';
	const PROFILE_FILTER = 'ddd_contract_public_profile_v1';
	const CLINIC_FILTER = 'ddd_contract_public_clinic_v1';
	const FOUNDER_FILTER = 'ddd_contract_founder_v1';









}

final class DDD_Helpers {
	use DDD_Helpers_Trait_1;

	const META = '_ddd_';
	const LEGACY_META = '_sdd_';


















}

/* Backward-compatible class names for existing adapters. */
if ( ! class_exists( 'SDD_Helpers' ) ) {
	class_alias( 'DDD_Helpers', 'SDD_Helpers' );
}
