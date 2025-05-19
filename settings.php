<?php
defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage(
        'local_customgradingform_renderer',
        get_string('pluginname', 'local_customgradingform_renderer')
    );

    // Enable maximum score validation
    $settings->add(new admin_setting_configcheckbox(
        'local_customgradingform_renderer/enablemaxlevelscore',
        get_string('enablemaxlevelscore', 'local_customgradingform_renderer'),
        get_string('enablemaxlevelscore_desc', 'local_customgradingform_renderer'),
        0 // Disabled by default
    ));

    // Maximum score per level
    $settings->add(new admin_setting_configtext(
        'local_customgradingform_renderer/maxlevelscore',
        get_string('maxlevelscore', 'local_customgradingform_renderer'),
        get_string('maxlevelscore_desc', 'local_customgradingform_renderer'),
        10,
        PARAM_FLOAT
    ));

    // Enable minimum score validation
    $settings->add(new admin_setting_configcheckbox(
        'local_customgradingform_renderer/enableminlevelscore',
        get_string('enableminlevelscore', 'local_customgradingform_renderer'),
        get_string('enableminlevelscore_desc', 'local_customgradingform_renderer'),
        0
    ));

    // Minimum score per level (fixed value: 0)
    $settings->add(new admin_setting_configtext(
        'local_customgradingform_renderer/minlevelscore',
        get_string('minlevelscore', 'local_customgradingform_renderer'),
        get_string('minlevelscore_desc', 'local_customgradingform_renderer'),
        0,
        PARAM_FLOAT
    ));

    $ADMIN->add('localplugins', $settings);
}
