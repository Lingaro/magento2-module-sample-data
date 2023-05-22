<?php

/**
 * Copyright © 2023 Lingaro sp. z o.o. All rights reserved.
 * See LICENSE for license details.
 */

namespace Lingaro\SampleData\Helper;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\Api\SearchCriteriaBuilderFactory;
use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\File\Size;
use Magento\Framework\Filesystem\Driver\File;

class Products
{
    /**
     * @var SearchCriteriaBuilderFactory
     */
    private $searchCriteriaBuilderFactory;
    /**
     * @var ProductRepositoryInterface
     */
    private $productRepository;
    /**
     * @var File
     */
    private $file;
    /**
     * @var array
     */
    private $imagePaths = [];
    /**
     * @var double
     */
    private $filesSize = 0;
    /**
     * @var Size
     */
    private $size;

    /**
     * Products constructor.
     * @param SearchCriteriaBuilderFactory $searchCriteriaBuilderFactory
     * @param ProductRepositoryInterface $productRepository
     * @param File $file
     * @param Size $size
     */
    public function __construct(
        SearchCriteriaBuilderFactory $searchCriteriaBuilderFactory,
        ProductRepositoryInterface $productRepository,
        File $file,
        Size $size
    ) {
        $this->searchCriteriaBuilderFactory = $searchCriteriaBuilderFactory;
        $this->productRepository = $productRepository;
        $this->file = $file;
        $this->size = $size;
    }

    /**
     * @return array
     */
    public function getImagePaths()
    {
        return array_unique($this->imagePaths);
    }

    /**
     * @param int $maxSize
     * @param array $filters
     * @return bool
     * @throws FileSystemException
     * @throws NoSuchEntityException
     */
    public function generateAllImageFilesForStoreProducts(
        ?int $maxSize,
        array $filters
    ): bool {
        if (!$products = $this->getProductCollection($filters)) {
            throw new NoSuchEntityException(__('No products in the selection'));
        }

        foreach ($products as $product) {
            $this->generateAllImageFilesForStoreProduct($product, $maxSize);
            if ($maxSize && !$this->checkDirectorySizeAgainstMaxSize($maxSize)) {
                return false;
            }

            // check children products only when we filter skus, when no filter all products will be considered
            if (!empty($filters) && !empty($childrenProducts = $this->getChildrenProducts($product))) {
                $result = $this->generateAllImageFilesForStoreProducts(
                    $maxSize,
                    ['entity_id' => $childrenProducts]
                );
                if (!$result) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * load product collection with given filters
     *
     * @param array $arrayFilters
     * @return array
     */
    protected function getProductCollection(array $arrayFilters): array
    {
        $searchCriteriaBuilder = $this->searchCriteriaBuilderFactory->create();

        if (!empty($arrayFilters)) {
            foreach ($arrayFilters as $key => $filter) {
                if (!empty($filter)) {
                    $searchCriteriaBuilder->addFilter($key, $filter, 'in');
                }
            }
        }

        return $this->productRepository->getList($searchCriteriaBuilder->create())->getItems();
    }

    /**
     * generate paths to all images in product selection
     *
     * @param ProductInterface $product
     * @param int|null $maxSize
     * @return bool
     * @throws FileSystemException
     */
    public function generateAllImageFilesForStoreProduct(
        ProductInterface $product,
        ?int $maxSize
    ): bool {
        foreach ($product->getMediaGalleryImages() as $image) {
            // check if this image is already counted
            if (in_array($image->getFile(), $this->getImagePaths())) {
                continue;
            }

            $this->imagePaths[] = $image->getFile();
            if ($maxSize) {
                if ($this->file->isFile($image->getPath())) {
                    $this->filesSize += $this->size->getFileSizeInMb($this->file->stat($image->getPath())['size'], 2);
                }
            }
        }
        return true;
    }

    /**
     * get children products
     *
     * @param ProductInterface $product
     * @return array
     */
    protected function getChildrenProducts(ProductInterface $product): array
    {
        if (!$product->getTypeInstance()->getRelationInfo()->getChildFieldName()) {
            return [];
        }

        return $product->getTypeInstance()->getChildrenIds($product->getId());
    }

    /**
     * check if images max size is reached
     *
     * @param int $maxSize
     * @return bool
     */
    protected function checkDirectorySizeAgainstMaxSize(int $maxSize) : bool
    {
        if (!$maxSize || !$this->filesSize) {
            return true;
        }

        if ($this->filesSize >= $maxSize) {
            return false;
        }

        return true;
    }
}
