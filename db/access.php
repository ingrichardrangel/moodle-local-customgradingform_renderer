<?php

defined('MOODLE_INTERNAL') || die();

$capabilities = [
    'local/customgradingform_renderer:import' => [
        'captype' => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes' => [
            'manager' => CAP_ALLOW,
        ],
    ],
];
