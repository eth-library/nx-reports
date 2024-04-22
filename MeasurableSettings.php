<?php

namespace Piwik\Plugins\NxReporting;
use Piwik\Settings\Setting;
use Piwik\Settings\FieldConfig;

/**
 * Defines Settings for NxReporting.
 *
 * Usage like this:
 * // require Piwik\Plugin\SettingsProvider via Dependency Injection eg in constructor of your class
 * $settings = $settingsProvider->getMeasurableSettings('NxReporting', $idSite);
 * $settings->appId->getValue();
 * $settings->contactEmails->getValue();
 */
class MeasurableSettings extends \Piwik\Settings\Measurable\MeasurableSettings
{
    /** @var Setting */
    public $reportsConfig;

    protected function init()
    {
        $this->reportsConfig = $this->makeReportsSetting();
    }

    private function makeReportsSetting()
    {
        $defaultValue = '{ "version": "0.1", "reports": [] }';
        $type = FieldConfig::TYPE_STRING;

        return $this->makeSetting('contact_email', $defaultValue, $type, function (FieldConfig $field) {
            $field->title = 'Reports configuration for this website';
            $field->inlineHelp = 'Configuration for NX Reporting Plugin (must be valid JSON). Provided by support@nextension.com. For log_action.type see https://developer.matomo.org/guides/database-schema#log-data-persistence-action-types';
            $field->uiControl = FieldConfig::UI_CONTROL_TEXTAREA;
            $field->uiControlAttributes = array('size' => 10);
            $field->introduction = 'NX Reporting';
            //$field->description = 'description';
            $field->validate = function ($value, $setting) {
                $parsedSettings = json_decode($value, true);
                if (is_null($parsedSettings)) {
                    throw new \Exception('Invalid JSON syntax' . print_r($parsedSettings, true));
                }
            };
        });
    }
}
