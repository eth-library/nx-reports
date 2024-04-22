<?php

namespace Piwik\Plugins\NxReporting\Reports;

use Piwik\Plugin\Report;
use Piwik\Plugin\ViewDataTable;

abstract class Base extends Report
{
    protected function init()
    {
        $this->categoryId = 'NxReporting_Category';
    }

    public function configureView(ViewDataTable $view)
    {
        if (!empty($this->dimension)) {
            $view->config->addTranslations(array('label' => $this->dimension->getName()));
        }
    }
}
