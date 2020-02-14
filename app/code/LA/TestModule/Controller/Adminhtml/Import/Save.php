<?php

namespace LA\TestModule\Controller\Adminhtml\Import;

use Magento\Backend\App\Action;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Catalog\Api\Data\ProductLinkInterfaceFactory as ProductLinkFactory;
use Magento\Framework\File\Csv;
use Magento\Catalog\Model\ResourceModel\Product;

/**
 * Class Save
 * @package LA\TestModule\Controller\Adminhtml\Import
 */
class Save extends Action
{
    /**
     * Product SKU Row number in csv file
     */
    const CSV_ROW_KEY_SKU = 0;

    /**
     * UP SELL Product SKUs Row number in csv file
     * Separated by a comma
     */
    const CSV_ROW_KEY_UPSELL = 1;

    /**
     * CROSS SELL Product SKUs Row number in csv file
     * Separated by a comma
     */

    const CSV_ROW_KEY_CROSSELL = 2;

    /**
     * Related Product SKUs Row number in csv file
     * Separated by a comma
     */
    const CSV_ROW_KEY_RELATED = 3;

    /**
     * Authorization level of a basic admin session
     *
     * @see _isAllowed()
     */
    const ADMIN_RESOURCE = 'LA_TestModule::import';

    /**
     * @var Csv
     */
    private $csvProcessor;

    /**
     * @var ProductLinkFactory
     */
    private $productLinkFactory;

    /**
     * @var ProductRepositoryInterface
     */
    private $productRepository;

    /**
     * @var Product
     */
    private $resourceModel;

    /**
     * @var array
     */
    private $productLinks;
    /**
     * @var Action\Context
     */
    private $context;

    /**
     * Save constructor.
     * @param Action\Context $context
     * @param Csv $csvProcessor
     * @param ProductLinkFactory $productLinkFactory
     * @param ProductRepositoryInterface $productRepository
     * @param Product $resourceModel
     */
    public function __construct(
        Action\Context $context,
        Csv $csvProcessor,
        ProductLinkFactory $productLinkFactory,
        ProductRepositoryInterface $productRepository,
        Product $resourceModel
    ) {
        parent::__construct($context);
        $this->csvProcessor = $csvProcessor;
        $this->productLinkFactory = $productLinkFactory;
        $this->productRepository = $productRepository;
        $this->resourceModel = $resourceModel;
        $this->context = $context;
    }

    /**
     * @return \Magento\Framework\App\ResponseInterface|\Magento\Framework\Controller\Result\Redirect|\Magento\Framework\Controller\ResultInterface
     */
    public function execute()
    {
        $resultRedirect = $this->resultRedirectFactory->create();

        try {
            $this->csvProcessor->setDelimiter(";");

            $data = $this->getRequest()->getParams();
            if (isset($data['importfile'][0]["full_path"])) {
                $fullPath = $data['importfile'][0]["full_path"];
                $updatedProductsCount = $this->updateProductLinks($fullPath);
            }
            $this->messageManager->addSuccessMessage(__('%1 products was successfully updated', $updatedProductsCount));
        } catch (\RuntimeException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        } catch (\Exception $e) {
            $this->messageManager->addExceptionMessage(
                $e,
                __('Something went wrong while import')
            );
        }

        return $resultRedirect->setPath('*/*/');
    }

    /**
     * @param $filePath
     * @return int
     * @throws \Exception
     */
    private function updateProductLinks($filePath)
    {
        $importProductRawData = $this->csvProcessor->getData($filePath);
        $count = 0;

        foreach ($importProductRawData as $rowIndex => $dataRow) {
            if ($rowIndex > 0) {
                try {
                    $this->productLinks = [];
                    $productSku = $dataRow[self::CSV_ROW_KEY_SKU];
                    $linkProduct = $this->productRepository->get($productSku);
                    if (!$linkProduct || !$linkProduct->getId()) {
                        throw new LocalizedException(
                            __("Product with cku %1 was skipped. Product not found", $productSku)
                        );
                    }

                    // create links for up sell products
                    $upSellSkus = $dataRow[self::CSV_ROW_KEY_UPSELL];
                    $this->createLink($upSellSkus, $productSku, 'upsell');

                    // create links for cross sell products
                    $crossSellSkus = $dataRow[self::CSV_ROW_KEY_CROSSELL];
                    $this->createLink($crossSellSkus, $productSku, 'crosssell');

                    // create links for related products
                    $relatedSkus = $dataRow[self::CSV_ROW_KEY_RELATED];
                    $this->createLink($relatedSkus, $productSku, 'related');

                    $linkProduct->setProductLinks($this->productLinks);
                    $this->saveProductModel($linkProduct);
                    $count++;
                } catch (LocalizedException $e) {
                    $this->messageManager->addErrorMessage($e);
                } catch (\Exception $e) {
                    $this->messageManager->addErrorMessage(
                        __("Product with cku %1 was skipped. Something went wrong while import", $productSku)
                    );
                }
            }
        }
        return $count;
    }

    /**
     * @param $product
     * @throws \Magento\Framework\Exception\CouldNotSaveException
     * @throws \Magento\Framework\Exception\InputException
     * @throws \Magento\Framework\Exception\StateException
     */
    private function saveProductModel($product)
    {
        $this->productRepository->save($product);
    }

    /**
     * @param $skus
     * @param $productSku
     * @param $linkType
     */
    private function createLink($skus, $productSku, $linkType)
    {
        if ($skus) {
            $skuArr = explode(",", $skus);
            $position = 0;
            foreach ($skuArr as $sku) {
                if ($sku && $this->checkIfProductExists($sku)) {
                    $link = $this->productLinkFactory->create();
                    $link->setSku($productSku)
                        ->setLinkedProductSku($sku)
                        ->setLinkType($linkType)
                        ->setPosition($position++);
                    $this->productLinks[] = $link;
                } else {
                    $this->messageManager->addErrorMessage(
                        __("Product with cku '%1' not found. Skipped", $productSku)
                    );
                }
            }
        }
    }

    /**
     * check If Product Exists
     * @param $sku
     * @return bool
     */
    private function checkIfProductExists($sku)
    {
        return boolval($this->resourceModel->getIdBySku($sku));
    }
}
