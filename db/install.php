<?php
defined('MOODLE_INTERNAL') || die();

use local_academicpanel\local\role_installer;

function xmldb_local_academicpanel_install() {
    role_installer::ensure_coordinator_role();
}
