<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Neo\ImageResizer\Service;

use Magento\Catalog\Helper\Image as ImageHelper;
use Magento\Catalog\Model\Product\Image\ParamsBuilder;
use Magento\Catalog\Model\View\Asset\ImageFactory as AssertImageFactory;
use Magento\Framework\App\Area;
use Magento\Framework\Exception\NotFoundException;
use Magento\Framework\Filesystem;
use Magento\Framework\Image;
use Magento\Framework\Image\Factory as ImageFactory;
use Magento\Catalog\Model\Product\Media\ConfigInterface as MediaConfig;
use Magento\Framework\App\State;
use Magento\Framework\View\ConfigInterface as ViewConfig;
use \Magento\Catalog\Model\ResourceModel\Product\Image as ProductImage;
use Magento\Theme\Model\Config\Customization as ThemeCustomizationConfig;
use Magento\Theme\Model\ResourceModel\Theme\Collection;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Catalog\Model\ResourceModel\Product\Gallery as Gallery;
use Magento\Framework\App\ResourceConnection as ResourceConnection;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class ImageResize
{
    /**
     * @var State
     */
    private $appState;

    /**
     * @var MediaConfig
     */
    private $imageConfig;

    /**
     * @var ProductImage
     */
    private $productImage;

    /**
     * @var ImageFactory
     */
    private $imageFactory;

    /**
     * @var ParamsBuilder
     */
    private $paramsBuilder;

    /**
     * @var ViewConfig
     */
    private $viewConfig;

    /**
     * @var AssertImageFactory
     */
    private $assertImageFactory;

    /**
     * @var ThemeCustomizationConfig
     */
    private $themeCustomizationConfig;

    /**
     * @var Collection
     */
    private $themeCollection;

    /**
     * @var Filesystem
     */
    private $mediaDirectory;

    /**
     * @var Filesystem
     */
    private $filesystem;

    private $gallery;

    private $connection;

    /**
     * @param State $appState
     * @param MediaConfig $imageConfig
     * @param ProductImage $productImage
     * @param ImageFactory $imageFactory
     * @param ParamsBuilder $paramsBuilder
     * @param ViewConfig $viewConfig
     * @param AssertImageFactory $assertImageFactory
     * @param ThemeCustomizationConfig $themeCustomizationConfig
     * @param Collection $themeCollection
     * @param Filesystem $filesystem
     * @internal param ProductImage $gallery
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     */
    public function __construct(
        State $appState,
        MediaConfig $imageConfig,
        ProductImage $productImage,
        ImageFactory $imageFactory,
        ParamsBuilder $paramsBuilder,
        ViewConfig $viewConfig,
        AssertImageFactory $assertImageFactory,
        ThemeCustomizationConfig $themeCustomizationConfig,
        Collection $themeCollection,
        Filesystem $filesystem,
        Gallery $gallery,
        ResourceConnection $connection
    ) {

       	$this->appState = $appState;
        $this->imageConfig = $imageConfig;
        $this->productImage = $productImage;
        $this->imageFactory = $imageFactory;
        $this->paramsBuilder = $paramsBuilder;
        $this->viewConfig = $viewConfig;
        $this->assertImageFactory = $assertImageFactory;
        $this->themeCustomizationConfig = $themeCustomizationConfig;
        $this->themeCollection = $themeCollection;
        $this->mediaDirectory = $filesystem->getDirectoryWrite(DirectoryList::MEDIA);
        $this->filesystem = $filesystem;
        $this->gallery = $gallery;
        $this->connection = $connection;
    }

    /**
     * Create resized images of different sizes from an original image
     * @param string $originalImageName
     * @throws NotFoundException
     */
    public function resizeFromImageName(string $originalImageName)
    {
        $originalImagePath = $this->mediaDirectory->getAbsolutePath(
            $this->imageConfig->getMediaPath($originalImageName)
        );
        if (!$this->mediaDirectory->isFile($originalImagePath)) {
            throw new NotFoundException(__('Cannot resize image "%1" - original image not found', $originalImagePath));
        }
        foreach ($this->getViewImages($this->getThemesInUse()) as $viewImage) {
            $this->resize($viewImage, $originalImagePath, $originalImageName);
        }
    }

    /**
     * Create resized images of different sizes from themes
     * @param array|null $themes
     * @return \Generator
     * @throws NotFoundException
     */
    public function resizeFromThemes(array $themes = null): \Generator
    {
        $count = $this->productImage->getCountAllProductImages();
        if (!$count) {
            throw new NotFoundException(__('Cannot resize images - product images not found'));
        }

        $productImages = $this->productImage->getAllProductImages();
        $viewImages = $this->getViewImages($themes ?? $this->getThemesInUse());
        
        foreach ($productImages as $image) {
            $originalImageName = $image['filepath'];
            $originalImagePath = $this->mediaDirectory->getAbsolutePath(
                $this->imageConfig->getMediaPath($originalImageName)
            );
            foreach ($viewImages as $viewImage) { 
                if($this->isResized($originalImageName)){
                    $this->resize($viewImage, $originalImagePath, $originalImageName);
                    $this->setResizeAtByImagePath($originalImageName);
                }                        
            }
            yield $originalImageName => $count;

        }
    }

    /**
     * Search the current theme
     * @return array
     */
    private function getThemesInUse(): array
    {
        $themesInUse = [];
        $registeredThemes = $this->themeCollection->loadRegisteredThemes();
        $storesByThemes = $this->themeCustomizationConfig->getStoresByThemes();
        $keyType = is_integer(key($storesByThemes)) ? 'getId' : 'getCode';
        foreach ($registeredThemes as $registeredTheme) {
            if (array_key_exists($registeredTheme->$keyType(), $storesByThemes)) {
                $themesInUse[] = $registeredTheme;
            }
        }
        return $themesInUse;
    }

    /**
     * Get view images data from themes
     * @param array $themes
     * @return array
     */
    private function getViewImages(array $themes): array
    {
        $viewImages = [];
        /** @var \Magento\Theme\Model\Theme $theme */
        foreach ($themes as $theme) {
            $config = $this->viewConfig->getViewConfig([
                'area' => Area::AREA_FRONTEND,
                'themeModel' => $theme,
            ]);
            $images = $config->getMediaEntities('Magento_Catalog', ImageHelper::MEDIA_TYPE_CONFIG_NODE);
            foreach ($images as $imageId => $imageData) {
                $uniqIndex = $this->getUniqueImageIndex($imageData);
                $imageData['id'] = $imageId;
                $viewImages[$uniqIndex] = $imageData;
            }
        }
        return $viewImages;
    }

    /**
     * Get unique image index
     * @param array $imageData
     * @return string
     */
    private function getUniqueImageIndex(array $imageData): string
    {
        ksort($imageData);
        unset($imageData['type']);
        return md5(json_encode($imageData));
    }

    /**
     * Make image
     * @param string $originalImagePath
     * @param array $imageParams
     * @return Image
     */
    private function makeImage(string $originalImagePath, array $imageParams): Image
    {
        $image = $this->imageFactory->create($originalImagePath);
        $image->keepAspectRatio($imageParams['keep_aspect_ratio']);
        $image->keepFrame($imageParams['keep_frame']);
        $image->keepTransparency($imageParams['keep_transparency']);
        $image->constrainOnly($imageParams['constrain_only']);
        $image->backgroundColor($imageParams['background']);
        $image->quality($imageParams['quality']);
        return $image;
    }

    /**
     * Resize image
     * @param array $viewImage
     * @param string $originalImagePath
     * @param string $originalImageName
     */
    private function resize(array $viewImage, string $originalImagePath, string $originalImageName)
    {
        $imageParams = $this->paramsBuilder->build($viewImage);
        $image = $this->makeImage($originalImagePath, $imageParams);
        $imageAsset = $this->assertImageFactory->create(
            [
                'miscParams' => $imageParams,
                'filePath' => $originalImageName,
            ]
        );

        if ($imageParams['image_width'] !== null && $imageParams['image_height'] !== null) {
            $image->resize($imageParams['image_width'], $imageParams['image_height']);
        }
        $image->save($imageAsset->getPath());
    }

    /**
     * @param $image
     */
    public function setResizeAtByImagePath(string $image){        
        
        $connection = $this->connection->getConnection();
        $sql = "SELECT value_id
        FROM `catalog_product_entity_media_gallery`
        WHERE `value` = '$image'";

        $valueId = $connection->fetchOne($sql);

        if($valueId){
            $sql = "UPDATE `catalog_product_entity_media_gallery` SET `resized_at` = NOW() WHERE `value_id` = '$valueId'";            
            $connection->query($sql);
        }
        
    }

    public function isResized($image){
        $connection = $this->connection->getConnection();
        $sql = "SELECT value_id
        FROM `catalog_product_entity_media_gallery`
        WHERE `resized_at` IS NOT NULL AND `value` = '$image'";
        $valueId = $connection->fetchOne($sql);
        if($valueId){
            return false;
        }else{
            return true;
        }

    }

}
