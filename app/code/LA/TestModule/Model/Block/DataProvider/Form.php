<?php

namespace LA\TestModule\Model\Block\DataProvider;

class Form extends \Magento\Ui\DataProvider\AbstractDataProvider
{
    /**
     * Get data
     *
     * @return array
     */
    public function getData()
    {
        return [];
    }

    /**
     * @inheritdoc
     */
    public function addFilter(\Magento\Framework\Api\Filter $filter)
    {
    }
}