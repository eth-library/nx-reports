<?php

namespace Piwik\Plugins\NxReporting\Reports;

use Piwik\Piwik;
use Piwik\Common;
use Piwik\Plugin\Report;
use Piwik\Plugin\ViewDataTable;
use Piwik\Plugins\CoreHome\Columns\LinkVisitActionIdPages;
use Piwik\View;
use Piwik\Visualization;
use Piwik\Plugins\CoreVisualizations\Visualizations\HtmlTable;

/**
 * This class defines a new report.
 *
 * See {@link http://developer.piwik.org/api-reference/Piwik/Plugin/Report} for more information.
 */
class GetNxReport extends Base
{
    private $columns = [];
    private $reportConfig = [];
    private $columnTranslations = [];
    private $widgetTitle;
    private $menuTitle;

    protected function init()
    {
        parent::init();

        $this->name          = Piwik::translate('NxReporting_Report');
        $this->dimension     = new LinkVisitActionIdPages();
        //$this->documentation = Piwik::translate('');

        // This defines in which order your report appears in the mobile app, in the menu and in the list of widgets
        $this->order = 1;

        // By default standard metrics are defined but you can customize them by defining an array of metric names
        //$this->metrics       = array('nb_visits', 'nb_hits', 'dimension1');
        //$this->metrics = array('nb_visits');
        //$this->metrics = array('nb_uniq_visitors', 'nb_visits', 'nb_actions');

        // Uncomment the next line if your report does not contain any processed metrics, otherwise default
        // processed metrics will be assigned
        //$this->processedMetrics = array();

        // Uncomment the next line if your report defines goal metrics
        //$this->hasGoalMetrics = false;

        // Uncomment the next line if your report should be able to load subtables. You can define any action here
        $this->actionToLoadSubTables = $this->action;

        // Uncomment the next line if your report always returns a constant count of rows, for instance always
        // 24 rows for 1-24hours
        // $this->constantRowsCount = true;

        // If a subcategory is specified, the report will be displayed in the menu under this menu item
        //$this->subcategoryId = $this->name;

        $idSite = Common::getRequestVar('idSite', 0, 'int');
        $idReport = Common::getRequestVar('idReport', '', 'string');

        if (strlen($idReport) > 0 && $idSite > 0) {
            // if not initialized from NxReporting class
            $settings = new \Piwik\Plugins\NxReporting\MeasurableSettings($idSite);
            $reportsConfig = json_decode($settings->reportsConfig->getValue() ?? '{}');

            foreach ($reportsConfig->reports as $index => $reportConfig) {
                if (($reportConfig->id) == $idReport) {
                    $this->setReportConfig($reportConfig, $index);
                }
            }
        }
    }

    /**
     * Here you can configure how your report should be displayed. For instance whether your report supports a search
     * etc. You can also change the default request config. For instance change how many rows are displayed by default.
     *
     * @param ViewDataTable $view
     */
    // public function configureView(ViewDataTable $view)
    // {
    //     $view->show_embedded_subtable = false;
    //     $view->show_table_performance = false;
    //     $view->show_related_reports = false;
    //     //var_dump('<pre>' . print_r($view->config, true) . '</pre>');

    //     //parent::configureView($view);

    //     // if (!empty($this->dimension)) {
    //     //     $view->config->addTranslations(array('label' => $this->dimension->getName()));
    //     // }

    //     // // $view->config->show_search = false;
    //     // // $view->requestConfig->filter_sort_column = 'nb_visits';
    //     // // $view->requestConfig->filter_limit = 10';

    //     // $view->config->columns_to_display = array_merge(array('label'), $this->metrics);

    //     $view->config->disable_all_rows_filter_limit = false;

    //     // The ViewDataTable must be configured so the display is perfect for the report.
    //     // We do this by modifying properties on the ViewDataTable::$config object.

    //     // Disable the 'Show All Columns' footer icon
    //     $view->config->show_table_all_columns = false;
    //     // The 'label' column will have 'Browser' as a title
    //     $view->config->addTranslation('label', 'journal');

    //     $view->config->columns_to_display = array(); //array_merge(array('label'), $this->metrics);
    // }
    public function configureView(ViewDataTable $view)
    {
        parent::configureView($view);
        //$view->show_embedded_subtable = true;

        $idReport = Common::getRequestVar('idReport', 0, 'string');
        if (!isset($idReport) || strlen($idReport) == 0) {
            return;
        }
        $view->requestConfig->request_parameters_to_modify['idReport'] = $idReport;
        // $view->config->show_search = false;

        // row evolution is not supported
        $view->config->disable_row_evolution = true;
        // fixed set of columns
        $view->config->show_table_all_columns = false;

        $view->requestConfig->filter_sort_column = 'nb_visits';
        $view->requestConfig->filter_sort_order  = 'asc';
        $view->requestConfig->filter_limit = 10;

        $view->config->addTranslation('label', $this->name);
        if (!empty($this->columnTranslations)) {
            foreach ($this->columnTranslations as $key => $value) {
                $view->config->addTranslation($key, $value);
            }
        }

        //$view->config->addTranslation('nb_unique_ft', Piwik::translate('NxReporting_Test'));
        //$view->config->addTranslation('nb_unique_downloads', 'PDF Downloads');

        // array('label', 'nb_downloads', 'nb_unique_downloads', 'nb_ft', 'nb_unique_ft')
        $extraColumns = !empty($this->reportConfig->extraColumns) ? (array)$this->reportConfig->extraColumns : [];
        foreach ($extraColumns as $key => $value) {
            if (!empty($value->label)) {
                $view->config->addTranslation($key, Piwik::translate($value->label));
            }
        }
        $view->config->columns_to_display = array_merge(array_keys($extraColumns), $this->columns, $this->metrics);
        $view->config->removeColumnToDisplay('nb_users');
        $view->config->removeColumnToDisplay('nb_actions');
    }

    /**
     * Here you can define related reports that will be shown below the reports. Just return an array of related
     * report instances if there are any.
     *
     * @return \Piwik\Plugin\Report[]
     */
    public function getRelatedReports()
    {
        return array(); // eg return array(new XyzReport());
    }

    public function getMetrics()
    {
        $metrics = parent::getMetrics();
        return $metrics;
    }

    /**
     * A report is usually completely automatically rendered for you but you can render the report completely
     * customized if you wish. Just overwrite the method and make sure to return a string containing the content of the
     * report. Don't forget to create the defined twig template within the templates folder of your plugin in order to
     * make it work. Usually you should NOT have to overwrite this render method.
     *
     * @return string
     */
    // public function render()
    // {
    //     $view = new View('@NxReporting/getJournalDownloadSummary');
    //     $view->myData = array();

    //     return $view->render();
    // }


    /**
     * By default your report is available to all users having at least view access. If you do not want this, you can
     * limit the audience by overwriting this method.
     *
     * @return bool
     */
    // public function isEnabled()
    // {
    //     return Piwik::hasUserSuperUserAccess();
    // }

    // public function getDefaultTypeViewDataTable() {
    //     return HtmlTable::ID;
    // }

    public function alwaysUseDefaultViewDataTable()
    {
        return true;
    }

    public function setReportConfig($report, $index = 0)
    {
        $this->name = Piwik::translate('NxReporting_Report_' . $report->name ?? $report->id);
        $this->menuTitle = Piwik::translate($this->name);
        $this->widgetTitle = Piwik::translate($this->name);
        $this->subcategoryId = Piwik::translate('NxReporting_Report_' . $report->id);
        $this->columns = array_merge(array('label'), ...array_column($report->columns, 'show') ?? array_column($report->columns, 'id'));
        $this->parameters = array('idReport' => $report->id);
        $this->order = 100 + $index;
        $this->reportConfig = $report;

        foreach ($report->columns as $column) {
            foreach ($column->results as $key) {
                $this->columnTranslations[$key ?? $column->id] = Piwik::translate('NxReporting_' . $key ?? $column->id);
            }
        }

        return true;
    }
}
