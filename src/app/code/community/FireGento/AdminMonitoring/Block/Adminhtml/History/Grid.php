<?php
/**
 * This file is part of a FireGento e.V. module.
 *
 * This FireGento e.V. module is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License version 3 as
 * published by the Free Software Foundation.
 *
 * This script is distributed in the hope that it will be useful, but WITHOUT
 * ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS
 * FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.
 *
 * PHP version 5
 *
 * @category  FireGento
 * @package   FireGento_AdminMonitoring
 * @author    FireGento Team <team@firegento.com>
 * @copyright 2013 FireGento Team (http://www.firegento.com)
 * @license   http://opensource.org/licenses/gpl-3.0 GNU General Public License, version 3 (GPLv3)
 */
/**
 * Displays the logging history grid
 *
 * @category FireGento
 * @package  FireGento_AdminMonitoring
 * @author   FireGento Team <team@firegento.com>
 */
class FireGento_AdminMonitoring_Block_Adminhtml_History_Grid
    extends Mage_Adminhtml_Block_Widget_Grid
{
    /**
     * Grid constructor
     */
    public function __construct()
    {
        parent::__construct();

        $this->setId('firegento_adminmonitoring_grid');

        $this->setDefaultSort('history_id');
        $this->setDefaultDir('desc');

        $this->setSaveParametersInSession(true);
    }

    /**
     * @param  mixed  $value
     * @param  string $key
     * @return string
     */
    private function formatCellContent($key, $value)
    {
        if (is_array($value)) {
            $value = print_r($value, true);
        }
        return  $this->entities($key . ': ' . $value) . '<br />';
    }

    /**
     * @param $string
     * @return string
     */
    private function entities($string)
    {
        return htmlspecialchars($string, ENT_QUOTES|ENT_COMPAT, 'UTF-8');
    }

    protected function _prepareCollection()
    {
        $collection = Mage::getModel('firegento_adminmonitoring/history')->getCollection();
        $collection->setOrder('history_id', 'DESC');
        $this->setCollection($collection);
        parent::_prepareCollection();
        return $this;
    }

    /**
     * @return FireGento_AdminMonitoring_Block_Adminhtml_History_Grid
     */
    protected function _prepareColumns()
    {
        $this->addColumn('history_id', array(
            'header' => Mage::helper('firegento_adminmonitoring')->__('ID'),
            'align' => 'right',
            'index' => 'history_id',
        ));

        $this->addColumn('created_at', array(
            'header' => Mage::helper('firegento_adminmonitoring')->__('Date/Time'),
            'index' => 'created_at',
            'type' => 'datetime',
        ));

        $this->addColumn('action', array(
            'header' => Mage::helper('firegento_adminmonitoring')->__('Action'),
            'index' => 'action',
            'type' => 'options',
            'options' => array(
                FireGento_AdminMonitoring_Helper_Data::ACTION_UPDATE => $this->__('Update'),
                FireGento_AdminMonitoring_Helper_Data::ACTION_INSERT => $this->__('Insert'),
                FireGento_AdminMonitoring_Helper_Data::ACTION_DELETE => $this->__('Delete'),
            )
        ));

        $this->addColumn('object_type', array(
            'header' => Mage::helper('firegento_adminmonitoring')->__('Object Type'),
            'index' => 'object_type',
        ));

        $this->addColumn('object_id', array(
            'header' => Mage::helper('firegento_adminmonitoring')->__('Object ID'),
            'index' => 'object_id',
            'type' => 'number',
        ));

        $this->addColumn('content', array(
            'header' => Mage::helper('firegento_adminmonitoring')->__('Content New'),
            'index' => 'content',
            'frame_callback' => array($this, 'showNewContent'),
        ));

        $this->addColumn('content_diff', array(
            'header' => Mage::helper('firegento_adminmonitoring')->__('Diff to getOrigData()'),
            'index' => 'content_diff',
            'frame_callback' => array($this, 'showOldContent'),
        ));

        $optionArray = array();
        $model = Mage::getModel('admin/user');
        $adminUsers = $model->getCollection();
        foreach ($adminUsers as $adminUser) {
            $optionArray[$adminUser->getId()] = $this->entities($adminUser->getUsername());
        }

        $this->addColumn('user_id', array(
            'header' => Mage::helper('firegento_adminmonitoring')->__('User'),
            'index' => 'user_id',
            'type' => 'options',
            'options' => $optionArray,
        ));

        $this->addColumn('user_name', array(
            'header' => Mage::helper('firegento_adminmonitoring')->__('User name logged'),
            'index' => 'user_name',
        ));

        $this->addColumn('ip', array(
            'header' => Mage::helper('firegento_adminmonitoring')->__('IP'),
            'index' => 'ip',
        ));

        $this->addColumn('user_agent', array(
            'header' => Mage::helper('firegento_adminmonitoring')->__('User Agent'),
            'index' => 'user_agent',
        ));

        $this->addColumn('revert', array(
            'header'    => Mage::helper('customer')->__('Revert'),
            'width'     => 10,
            'sortable'  => false,
            'filter'    => false,
            'renderer'  => 'firegento_adminmonitoring/adminhtml_history_grid_revert',
        ));

        parent::_prepareColumns();
        return $this;
    }

    /**
     * @param $row
     * @return bool|string
     */
    public function getRowUrl($row)
    {
        $transport = new Varien_Object();
        Mage::dispatchEvent('firegento_adminmonitoring_rowurl', array('history' => $row, 'transport' => $transport));
        return $transport->getRowUrl();
    }

    /**
     * @param  string $content
     * @return mixed
     */
    private function decodeContent($content)
    {
        $content = html_entity_decode($content);
        return json_decode($content, true);
    }

    /**
     * @param  string                                  $newContent
     * @param  FireGento_AdminMonitoring_Model_History $row
     * @return string
     */
    public function showNewContent($newContent, FireGento_AdminMonitoring_Model_History $row)
    {
        $cell = '';

        if ($row->isDelete()) {
            $cell = '';
        } else {
            $oldContent = $row->getContentDiff();
            $oldContent = $this->decodeContent($oldContent);

            $newContent = $this->decodeContent($newContent);

            if (is_array($oldContent) && is_array($newContent)) {
                if (count($oldContent) > 0) {
                    $showContent = $oldContent;
                } else {
                    $showContent = $newContent;
                }
                foreach ($showContent as $key => $value) {
                    if (array_key_exists($key, $newContent)) {
                        $attributeName = Mage::helper('firegento_adminmonitoring')->getAttributeNameByTypeAndCode($row->getObjectType(), $key);
                        $cell .= $this->formatCellContent($attributeName, $newContent[$key]);
                    }
                }
            }
        }
        return $this->wrapColor($cell, '#00ff00');
    }


    /**
     * @param  string                                  $oldContent
     * @param  FireGento_AdminMonitoring_Model_History $row
     * @return string
     */
    public function showOldContent($oldContent, FireGento_AdminMonitoring_Model_History $row)
    {
        $cell = '';
        $oldContent = $this->decodeContent($oldContent);

        if (is_array($oldContent)) {
           if (count($oldContent) > 0) {
               foreach ($oldContent as $key => $value) {
                   $attributeName = Mage::helper('firegento_adminmonitoring')->getAttributeNameByTypeAndCode($row->getObjectType(), $key);
                   $cell .= $this->formatCellContent($attributeName, $value);
               }
           } else {
               return $this->__('not available');
           }
        }
        return $this->wrapColor($cell, '#ff0000');
    }

    /**
     * @param  string $string
     * @param  string $color
     * @return string
     */
    private function wrapColor($string, $color)
    {
        return '<div style="font-weight: bold; color: ' . $color . '; overflow: auto; max-height: 100px; max-width: 400px;">' . $string . '</div>';
    }
}
