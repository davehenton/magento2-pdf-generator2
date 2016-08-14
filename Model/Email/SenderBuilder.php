<?php
/**
 * EaDesgin
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License (AFL 3.0)
 * that is bundled with this package in the file LICENSE_AFL.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/afl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@eadesign.ro so we can send you a copy immediately.
 *
 * @category    eadesigndev_pdfgenerator
 * @copyright   Copyright (c) 2008-2016 EaDesign by Eco Active S.R.L.
 * @license     http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 */

namespace Eadesigndev\Pdfgenerator\Model\Email;

use Magento\Sales\Model\Order\Email\Container\IdentityInterface;
use Magento\Sales\Model\Order\Email\Container\Template;
use Eadesigndev\Pdfgenerator\Helper\Pdf;
use Eadesigndev\Pdfgenerator\Helper\Data;
use Eadesigndev\Pdfgenerator\Model\ResourceModel\Pdfgenerator\CollectionFactory as templateCollectionFactory;
use Magento\Framework\Stdlib\DateTime\DateTime;


class SenderBuilder extends \Magento\Sales\Model\Order\Email\SenderBuilder
{

    /**
     * @var Pdf
     */
    protected $_helper;

    /**
     * @var Data
     */
    private $_dataHelper;

    /**
     * @var \Eadesigndev\Pdfgenerator\Model\ResourceModel\Pdfgenerator\Collection
     */
    protected $_templateCollection;

    /**
     * @var
     */
    protected $_dateTime;

    /**
     * SenderBuilder constructor.
     * @param Template $templateContainer
     * @param IdentityInterface $identityContainer
     * @param TransportBuilder $transportBuilder
     * @param Pdf $_helper
     * @param templateCollectionFactory $_templateCollection
     * @param DateTime $_dateTime
     */
    public function __construct(
        Template $templateContainer,
        IdentityInterface $identityContainer,
        TransportBuilder $transportBuilder,
        Pdf $_helper,
        Data $_dataHelper,
        templateCollectionFactory $_templateCollection,
        DateTime $_dateTime
    )
    {
        $this->_helper = $_helper;
        $this->_dataHelper = $_dataHelper;
        //todo move collection to helper and get the same on the plugins
        $this->_templateCollection = $_templateCollection;
        $this->_dateTime = $_dateTime;
        parent::__construct($templateContainer, $identityContainer, $transportBuilder);
    }

    /**
     * Add attachment to the main mail
     */
    public function send()
    {

        $vars = $this->templateContainer->getTemplateVars();
        $this->_checkInvoice($vars);

        parent::send();
    }

    /**
     * Add attachment to the css/bcc mail
     */
    public function sendCopyTo()
    {
        $vars = $this->templateContainer->getTemplateVars();
        $this->_checkInvoice($vars);
        parent::sendCopyTo();
    }

    /**
     * Get the active template
     *
     * @param $invoice
     * @return \Magento\Framework\DataObject
     */
    private function _getTemplateStatus($invoice)
    {

        //todo move this to a helper!
        $invoiceStore = $invoice->getOrder()->getStoreId();

        $collection = $this->_templateCollection->create();
        $collection->addStoreFilter($invoiceStore);
        $collection->addFieldToFilter('is_active', \Eadesigndev\Pdfgenerator\Model\Source\TemplateActive::STATUS_ENABLED);
        $collection->addFieldToFilter('template_default', \Eadesigndev\Pdfgenerator\Model\Source\AbstractSource::IS_DEFAULT);

        return $collection->getLastItem();
    }

    /**
     *
     * Check if we need to send the invoice email
     *
     * @param $vars
     * @return $this
     */
    private function _checkInvoice($vars)
    {
        //todo move all this to a helper
        if (!class_exists('mPDF') || !$this->_dataHelper->isEmail()) {
            return $this;
        }

        if ($invoice = $vars['invoice']) {
            if ($invoice instanceof \Magento\Sales\Model\Order\Invoice) {

                $helper = $this->_helper;

                $helper->setInvoice($invoice);
                $template = $this->_getTemplateStatus($invoice);

                if (!$template->getId()) {
                    return $this;
                }

                $helper->setTemplate($template);

                $pdfFileData = $helper->template2Pdf();

                $date = $this->_dateTime->date('Y-m-d_H-i-s');

                $this->transportBuilder->addAttachment(
                    $pdfFileData['filestream']
                    , \Zend_Mime::TYPE_OCTETSTREAM
                    , \Zend_Mime::DISPOSITION_ATTACHMENT
                    , \Zend_Mime::ENCODING_BASE64
                    , $pdfFileData['filename'] . $date . '.pdf'

                );

            }
        }

        return $this;
    }
}
