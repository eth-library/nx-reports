<?php

namespace Piwik\Plugins\NxReporting;

use Piwik\Config;
use Piwik\Metrics;
use Piwik\Plugins\Actions\Metrics as ActionsMetrics;
use Piwik\Plugins\CustomDimensions\Dao\Configuration;
use Piwik\Plugins\CustomDimensions\Dao\LogTable;
use Piwik\Tracker;
use Piwik\ArchiveProcessor;
use Piwik\DataTable;
use Piwik\DataTable\Row;
use Piwik\Plugin\SettingsProvider;
use Psr\Log\LoggerInterface;
use Piwik\Container\StaticContainer;

class Archiver extends \Piwik\Plugin\Archiver
{
  private $processor;
  private $maximumRowsInDataTable;

  function __construct($processor)
  {
    parent::__construct($processor);
    $this->processor = $processor;

    $this->maximumRowsInDataTable = intval(Config::getInstance()->General['datatable_archiving_maximum_rows_nxreporting'] ?? 5000);
    //var_dump($this->maximumRowsInDataTable);
  }

  private function buildDataTableForReport($idSite, $report)
  {
    $logAggregator = $this->getLogAggregator();

    // result data table
    $dataTable = new DataTable();

    // single rollup / groupby dimension
    $groupBy = $report->groupBy;
    $extraColumnsConfig = empty($report->extraColumns) ? [] : (array)$report->extraColumns;
    // $query = $logAggregator->queryActionsByDimension(
    //   $dimensions = array($groupBy),
    //   $where = "idsite = {$idSite} AND log_link_visit_action.{$groupBy} IS NOT NULL",
    //   // the following does not seem to give correct results for visitors / visits
    //   // instead, it returns the index values 1 and 2
    //   $additionalSelects = array_keys($extraColumnsConfig),
    //   $metrics = [Metrics::INDEX_NB_UNIQ_VISITORS, Metrics::INDEX_NB_VISITS],
    //   //$rankingQuery = false,
    //   //$joinLogActionOnColumn = [$groupBy]
    // );
    $query = $logAggregator->queryActionsByDimension(
      $dimensions = array($groupBy),
      $where = "idsite = " . $idSite . " AND log_link_visit_action." . $groupBy . " IS NOT NULL",
      $additionalSelects = array_keys($extraColumnsConfig ?? []),
      $metrics = [Metrics::INDEX_NB_UNIQ_VISITORS, Metrics::INDEX_NB_VISITS, Metrics::INDEX_NB_ACTIONS],
      $rankingQuery = false,
      //$joinLogActionOnColumn = ['idaction_url_ref']
    );

    //var_dump($query);

    // use column names instead of numeric identifiers
    $metricsMapping = Metrics::getMappingFromIdToName();

    // for each row, collect the relevant base metrics
    $rowsByDim = [];
    while ($row = $query->fetch()) {
      $extraColumns = [];
      foreach ($extraColumnsConfig as $key => $col) {
        $extraColumns[$key] = $row[$key];

        // if (isset($col->transform)) {
        //   $extraColumns[$key] = 'test';
        //   var_dump($col);
        //   $extraColumns[$key] = preg_replace('/.*pid=(.*?)/', '$1', $row[$key]);
        // }
      }

      $mrow = new Row(array(
        Row::COLUMNS => array_merge(
          array(
            'label' => $row[$groupBy],
            $metricsMapping[Metrics::INDEX_NB_VISITS] => $row[Metrics::INDEX_NB_VISITS],
            $metricsMapping[Metrics::INDEX_NB_UNIQ_VISITORS] => $row[Metrics::INDEX_NB_UNIQ_VISITORS],
            #$metricsMapping[Metrics::INDEX_NB_ACTIONS] => $row[Metrics::INDEX_NB_ACTIONS],
          ),
          $extraColumns
        ),
        #Row::METADATA => array('url' => 'http://example.com')
      ));

      $rowsByDim[$row[$groupBy]] = $mrow;
      $dataTable->addRow($mrow);
    }

    // calculate columns and add to rows
    foreach ($report->columns ?? [] as $column) {
      $whereExpression = $column->where ? 'AND ' . $column->where : '';

      // see https://developer.matomo.org/guides/database-schema
      $query = $this->getLogAggregator()->queryActionsByDimension(
        $dimensions = array($groupBy),
        $where = "{$groupBy} IS NOT NULL {$whereExpression}",
        $additionalSelects = $column->selects,
        $metrics = array(Metrics::INDEX_NB_ACTIONS),
        false,
        $joinLogActionOnColumn = $column->joinColumns
      );

      while ($row = $query->fetch()) {
        $vrow = $rowsByDim[$row[$groupBy]];

        foreach ($column->results as $resultCol) {
          $vrow->addColumn($resultCol, $row[$resultCol]);
        }
      }
    }

    // skip column operations on extra/informational columns
    $columnAggregationOps = [];
    foreach (array_keys($extraColumnsConfig ?? []) as $key) {
      $columnAggregationOps[$key] = 'skip';
    }
    $dataTable->setMetadata(DataTable::COLUMN_AGGREGATION_OPS_METADATA_NAME, $columnAggregationOps);

    return $dataTable;
  }

  public function aggregateDayReport()
  {
    $idSite = $this->processor->getParams()->getSite()->getId();

    $settings = new \Piwik\Plugins\NxReporting\MeasurableSettings($idSite);
    $reportsConfig = json_decode($settings->reportsConfig->getValue() ?? '{}');
    $reports = $reportsConfig->reports ?? [];

    foreach ($reports as $report) {
      //var_dump($report);
      $dataTable = $this->buildDataTableForReport($idSite, $report);
      $this->getProcessor()->insertBlobRecord("NxReporting_report_{$report->id}", $dataTable->getSerialized($this->maximumRowsInDataTable));
    }
  }

  public static function mergeExtraColumns($a, $b)
  {
    if (!empty($a) && !empty($b) && $a != $b) {
      return $a . ' / ' . $b;
    }
    if (!empty($a)) return $a;
    if (!empty($b)) return $b;
    return null;
  }

  public function aggregateMultipleReports()
  {
    // $this->getProcessor()->aggregateDataTableRecords(
    //   $this->getRecordNames(),
    //   $this->maximumRowsInDataTableLevelZero,
    //   $this->maximumRowsInSubDataTable,
    //   $columnToSort = Metrics::INDEX_NB_VISITS,
    //   $columnsAggregationOperation,
    //   $columnsToRenameAfterAggregation = null,
    //   $countRowsRecursive = array());

    $archiveProcessor = $this->getProcessor();

    $idSite = $this->processor->getParams()->getSite()->getId();
    $settings = new \Piwik\Plugins\NxReporting\MeasurableSettings($idSite);
    $reportsConfig = json_decode($settings->reportsConfig->getValue() ?? '{}');
    $reports = $reportsConfig->reports ?? [];

    foreach ($reports as $report) {
      // improvement idea: could do this based on metadata of columns ('skip')
      $extraColumnsConfig = empty($report->extraColumns) ? [] : (array)$report->extraColumns;
      $aggregationOperations = [];
      foreach (array_keys($extraColumnsConfig ?? []) as $key) {
        $aggregationOperations[$key] = 'Piwik\Plugins\NxReporting\Archiver::mergeExtraColumns';
      }

      //var_dump($aggregationOperations);
      $archiveProcessor->aggregateDataTableRecords(
        "NxReporting_report_{$report->id}",
        $this->maximumRowsInDataTable,
        null,
        null,
        $aggregationOperations
      );
    }
  }
}
