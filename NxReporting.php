<?php

namespace Piwik\Plugins\NxReporting;

use Piwik\Plugin;
use Piwik\Common;

class NxReporting extends \Piwik\Plugin
{
    private $isInstalled;

    public function registerEvents()
    {
        if (!$this->isInstalled()) {
            return null;
        }

        return [
            'Report.addReports' => 'addReports',
            'Translate.getClientSideTranslationKeys' => 'getClientSideTranslationKeys',
        ];
    }

    private function isInstalled()
    {
        if (!isset($this->isInstalled)) {
            $names = Plugin\Manager::getInstance()->getInstalledPluginsName();
            // installed plugins are not yet loaded properly

            if (empty($names)) {
                return false;
            }

            $this->isInstalled = Plugin\Manager::getInstance()->isPluginInstalled($this->pluginName);
        }

        return $this->isInstalled;
    }

    // see source of CustomDimensions plugin
    private function getIdSite()
    {
        $idSite = Common::getRequestVar('idSite', 0, 'int');

        if (!$idSite) {
            // fallback for eg API.getReportMetadata which uses idSites
            $idSite = Common::getRequestVar('idSites', 0, 'int');

            if (!$idSite) {
                $idSite = Common::getRequestVar('idSites', 0, 'array');
                if (is_array($idSite) && count($idSite) === 1) {
                    $idSite = array_shift($idSite);
                    if (is_numeric($idSite)) {
                        return $idSite;
                    }
                }

                return;
            }
        }

        return $idSite;
    }

    public function getSettingsForSite($idSite)
    {
        $settings = new \Piwik\Plugins\NxReporting\MeasurableSettings($idSite);
        $reportsConfig = json_decode($settings->reportsConfig->getValue() ?? '{}');
        return $reportsConfig;
    }

    public function addReports(&$instances)
    {
        $idSite = $this->getIdSite();
        if (!$idSite) {
            return;
        }

        $nxConfig = $this->getSettingsForSite($idSite);
        $reportsConfig = $nxConfig->reports ?? [];

        foreach ($reportsConfig as $index => $reportConfig) {
            if (isset($reportConfig->display) && !$reportConfig->display) {
                continue;
            }

            $report = new Reports\GetNxReport();
            $report->setReportConfig($reportConfig, $index);
            $instances[] = $report;
        }
    }

    public function getClientSideTranslationKeys(&$translationKeys)
    {
        $idSite = $this->getIdSite();
        if (!$idSite) {
            return;
        }

        $nxConfig = $this->getSettingsForSite($idSite);
        $reportsConfig = $nxConfig->reports ?? [];

        foreach ($reportsConfig as $index => $reportConfig) {
            $translationKeys[] = 'NxReporting_Report_' . $reportConfig->name;
        }

        $translationKeys[] = 'NxReporting_Test';
    }
}
