<?php

namespace ImaginationMedia\RuleFix\Model\Rule\Condition\Product;

use Magento\Catalog\Model\ResourceModel\Product;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable;
use Magento\Catalog\Model\ProductRepository;

class Plugin
{
    /**
     * @var ProductRepository
     */
    protected $productRepository;

    /**
     * @var Configurable
     */
    protected $configurable;

    /**
     * @var array
     */
    protected $availableCategoryIdsCache = [];

    /**
     * Plugin constructor.
     * @param ProductRepository $productRepository
     * @param Configurable $configurable
     */
    public function __construct(
        ProductRepository $productRepository,
        Configurable $configurable
    ) {
        $this->productRepository = $productRepository;
        $this->configurable = $configurable;
    }

    /**
     * Fix to get categories from configurable parent if the product is simple and non visible.
     * @param Product $subject
     * @param callable $proceed
     * @param $object
     * @return mixed
     */
    public function aroundGetAvailableInCategories(Product $subject, callable $proceed, $object)
    {
        $entityId = (int)$object->getEntityId();
        if($this->productRepository->getById($entityId)->getTypeId() == "configurable"){
            $this->setCategoriesForChilds($entityId);
        }
        return $this->_getAvailableInCategories($subject, $object);
    }

    /**
     * Set to simple products the same categories from configurable parent.
     * @param $parentId
     */
    private function setCategoriesForChilds($parentId){
        $_product = $this->productRepository->getById($parentId);
        $_children = $_product->getTypeInstance()->getUsedProducts($_product);
        $categories = $_product->getCategoryIds();
        foreach ($_children as $child){
            if(!array_key_exists($child->getId(), $this->availableCategoryIdsCache))
                $this->availableCategoryIdsCache[$child->getId()] = $categories;
            else{
                foreach ($categories as $category){
                    if(!in_array($category, $this->availableCategoryIdsCache[$child->getId()])){
                        array_push($this->availableCategoryIdsCache[$child->getId()], $category);
                    }
                }
            }
        }
    }

    /**
     * Retrieve category ids where product is available
     *
     * @param \Magento\Catalog\Model\Product $object
     * @return array
     */
    private function _getAvailableInCategories(Product $subject, $object)
    {
        $entityId = (int)$object->getEntityId();
        if (!isset($this->availableCategoryIdsCache[$entityId])) {
            $this->availableCategoryIdsCache[$entityId] = $subject->getConnection()->fetchCol(
                $subject->getConnection()->select()->distinct()->from(
                    $subject->getTable('catalog_category_product_index'),
                    ['category_id']
                )->where(
                    'product_id = ? AND is_parent = 1',
                    $entityId
                )->where(
                    'visibility != ?',
                    \Magento\Catalog\Model\Product\Visibility::VISIBILITY_NOT_VISIBLE
                )
            );
        }
        return $this->availableCategoryIdsCache[$entityId];
    }
}
