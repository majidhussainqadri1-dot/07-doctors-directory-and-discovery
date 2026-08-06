<?php
defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

/*
 * Non-destructive by default. Canonical directory projections, reports, audit,
 * saves and event evidence are retained to prevent accidental loss and to
 * preserve privacy/accountability workflows. A separately authenticated,
 * backed-up and audited purge operation must be supplied by an operations tool;
 * uninstall itself never drops tables or deletes user data.
 */
