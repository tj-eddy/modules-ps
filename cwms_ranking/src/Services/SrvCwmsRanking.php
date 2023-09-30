<?php

declare(strict_types=1);

namespace CrownMakers\CR\Services;

use Tools;
/**
 * Class SrvCwmsRanking
 * @package CrownMakers\CR\Services
 */
abstract class SrvCwmsRanking
{
    /**
     * @param $idProduct
     * @param $idShop
     * @return mixed
     */
    public static function getBonusRankingProduct($idProduct, $idShop)
    {
        return \Db::getInstance()->getRow("
            SELECT bonus_ranking 
            FROM " . _DB_PREFIX_ . "product_shop 
            WHERE id_shop = $idShop AND id_product=$idProduct")['bonus_ranking'];
    }

    /**
     * @param $idManufacturer
     * @param $idShop
     * @return mixed
     */
    public static function getBonusRankingManufacturer($idManufacturer, $idShop)
    {
        return \Db::getInstance()->getRow("
            SELECT bonus_ranking 
            FROM " . _DB_PREFIX_ . "manufacturer_shop 
            WHERE id_shop=$idShop AND id_manufacturer=$idManufacturer")['bonus_ranking'];
    }

    /**
     * @param $idProduct
     * @return float|int
     */
    public static function calculateRanking($idProduct)
    {
        $totalRanking = 0;
        $idShop = \Context::getContext()->shop->id;
        $idLang = \Context::getContext()->language->id;
        $stockPonderation = (int)\Configuration::get("PONDERATION_STOCK", $idLang, null, $idShop);
        $productPonderation = (int)\Configuration::get("PONDERATION_PRODUCT", $idLang, null, $idShop);
        $manufacturerPonderation = (int)\Configuration::get("PONDERATION_MANUFACTURER", $idLang, null, $idShop);


        $product = new \Product($idProduct);

        if ((int)$product->id_manufacturer>0 && is_object($product)){
            $productRanking = (int)self::getBonusRankingProduct($idProduct, $idShop);
            $stockRanking = (int) \StockAvailable::getQuantityAvailableByProduct($idProduct, null, $idShop);
            $manufacturerRanking = (int)self::getBonusRankingManufacturer((int)$product->id_manufacturer, $idShop);

            $totalRanking = (int)(($stockPonderation * $stockRanking) +
                ($productPonderation * $productRanking) +
                ($manufacturerPonderation * $manufacturerRanking));
        }
        \Db::getInstance()->update('product',['total_ranking'=>$totalRanking],"id_product=$idProduct AND id_shop_default=$idShop");

        return $totalRanking;
    }


    /**
     * @param $data
     * @return int
     */
    public static function calculateRankingByManufacturer(array $data)
    {
        $totalRanking = 0;
        $idShop = \Context::getContext()->shop->id;
        $idLang = \Context::getContext()->language->id;
        $stockPonderation = (int)\Configuration::get("PONDERATION_STOCK", $idLang, null, $idShop);
        $productPonderation = (int)\Configuration::get("PONDERATION_PRODUCT", $idLang, null, $idShop);
        $manufacturerPonderation = (int)\Configuration::get("PONDERATION_MANUFACTURER", $idLang, null, $idShop);

        $id_manufacturer =(int) $data['id_manufacturer'];
        $bonus_ranking_manufacturer =(int) $data['bonus_ranking_manufacturer'];
        if ($id_manufacturer>0 && $bonus_ranking_manufacturer>0){
            $products = \Manufacturer::getProducts($id_manufacturer,$idLang,0,100000);

            foreach ($products as $product){
                $idProduct = $product['id_product'];
                $productRanking = (int)self::getBonusRankingProduct($idProduct, $idShop);
                $stockRanking = (int) \StockAvailable::getQuantityAvailableByProduct($idProduct, null, $idShop);
                $totalRanking = (int)(($stockPonderation * $stockRanking) +
                    ($productPonderation * $productRanking) +
                    ($manufacturerPonderation * $bonus_ranking_manufacturer));
                \Db::getInstance()->update('product',['total_ranking'=>$totalRanking],"id_product=$idProduct AND id_manufacturer=$id_manufacturer AND id_shop_default=$idShop");
            }
        }

        return $totalRanking;
    }

    /**
     * @param $search
     * @param $replace
     * @return bool
     */
    public static function replaceSortOrderField($search,$replace)
    {
        $status = false;
        $serviceProviderFile = _PS_ROOT_DIR_ . '/modules/ps_facetedsearch/src/Product/SearchProvider.php';
        $fileContent = Tools::file_get_contents($serviceProviderFile);
        $override = 0;
        $re = '/\''.$replace.'\'/s';
        preg_match($re, $fileContent, $matches);
        if (!count($matches)) {
            $override = 1;
            $re = '/\''.$search.'\'/s';
            $subst = '\''.$replace.'\'';
            $fileContent = preg_replace($re, $subst, $fileContent, 1);
        }
        if ($override) {
            file_put_contents($serviceProviderFile, $fileContent);
            $status = true;
        }

        return $status;
    }
}
