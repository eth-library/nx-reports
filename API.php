<?php

namespace Piwik\Plugins\NxReporting;

use Piwik\DataTable;
use Piwik\DataTable\Row;
use Piwik\Archive;
use Piwik\Piwik;
use Piwik\Common;
use Piwik\Metrics\Sorter;
use Piwik\Metrics\Sorter\Config;

/**
 * API for plugin NxReporting
 *
 * @method static \Piwik\Plugins\NxReporting\API getInstance()
 */
class API extends \Piwik\Plugin\API
{
    private $config;
    private $sorter;

    /**
     * Another example method that returns a data table.
     * @param int    $idSite
     * @param string $period
     * @param string $date
     * @param bool|string $segment
     * @return DataTable
     */
    public function getNxReport($idSite, $period, $date, $segment = false, $expanded = false, $flat = false, $idSubtable = null)
    {
        Piwik::checkUserHasViewAccess($idSite);
        $idReport = Common::getRequestVar('idReport', 'journals', 'string');

        $settings = new \Piwik\Plugins\NxReporting\MeasurableSettings($idSite);
        $reportsConfig = json_decode($settings->reportsConfig->getValue() ?? '{}');
        $report = current(array_filter($reportsConfig->reports ?? [], function ($v) use ($idReport) {
            return $v->id == $idReport;
        }, ARRAY_FILTER_USE_BOTH));

        $dataTable = Archive::createDataTableFromArchive('NxReporting_report_' . $idReport, $idSite, $period, $date, $segment, $expanded, $flat, $idSubtable);
        if (isset($idSubtable) && $dataTable->getRowsCount()) {
            //$parentTable = Archive::createDataTableFromArchive($record, $idSite, $period, $date, $segment);
        }

        // sort datatable
        $filterSortColumn = Common::getRequestVar('filter_sort_column', false, 'string');
        $filterSortOrder = Common::getRequestVar('filter_sort_order', false, 'string');

        $this->config = new Config();
        $this->config->primaryColumnToSort = $filterSortColumn;
        if ($filterSortOrder === 'desc') {
            $this->config->primarySortOrder = SORT_DESC;
        } else {
            $this->config->primarySortOrder = SORT_ASC;
        }
        $this->config->primarySortFlags = SORT_NUMERIC;
        $this->sorter = new Sorter($this->config);
        $this->sorter->sort($dataTable);

        // exclude extra columns from aggregation
        if (!empty($report->extraColumns)) {
            $colNames = array_keys((array)$report->extraColumns);
            $defs = [];
            foreach ($colNames as $col) {
                $defs[$col] = 'skip';
            }
            $dataTable->setMetadata(DataTable::COLUMN_AGGREGATION_OPS_METADATA_NAME, $defs);
        }


        return $dataTable;
    }
}
