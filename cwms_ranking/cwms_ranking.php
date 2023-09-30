<?php
/**
 * 2007-2023 PrestaShop
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License (AFL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/afl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade PrestaShop to newer
 * versions in the future. If you wish to customize PrestaShop for your
 * needs please refer to http://www.prestashop.com for more information.
 *
 * @author    PrestaShop SA <contact@prestashop.com>
 * @copyright 2007-2023 PrestaShop SA
 * @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 *  International Registered Trademark & Property of PrestaShop SA
 */

use CrownMakers\CR\Services\SrvCwmsRanking;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\FormBuilderInterface;

if (!defined('_PS_VERSION_')) {
    exit;
}
if (file_exists(__DIR__ . '/src/Services/SrvCwmsRanking.php')) {
    require_once __DIR__ . '/src/Services/SrvCwmsRanking.php';
}

/**
 * Class Cwms_ranking
 */
class Cwms_ranking extends Module
{

    const TAB_CLASS_NAME = 'AdminCwmsRanking';
    const ROUTE_NAME = 'cwms_ranking_index';
    const MESSAGE_ERROR = 'The bonus ranking value must be between 0 and 100';
    const REPLACES = ['Best sellers' => 'Relevances',
        'Relevance' => 'Best sellers',
        'Relevances' => 'Relevance',];


    protected $idShop;
    protected $idLang;

    /**
     * Cwms_ranking constructor.
     */
    public function __construct()
    {
        $this->name = 'cwms_ranking';
        $this->tab = 'administration';
        $this->version = '1.0.0';
        $this->author = 'CrownMakers';
        $this->need_instance = 1;

        /**
         * Set $this->bootstrap to true if your module is compliant with bootstrap (PrestaShop 1.6)
         */
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('Ranking');
        $this->description = $this->l('This is a ranking module');
        $this->confirmUninstall = $this->l('Are you sure you want to uninstall this module ? ');
        $this->ps_versions_compliancy = array('min' => '1.7.8.0', 'max' => _PS_VERSION_);

        $this->addTab(self::ROUTE_NAME, self::TAB_CLASS_NAME);

        $this->idShop = Context::getContext()->shop->id;
        $this->idLang = Context::getContext()->language->id;

    }

    /**
     * @param $routeName
     * @param $className
     */
    private function addTab($routeName, $className)
    {
        $tabNames = [];
        foreach (Language::getLanguages(true) as $lang) {
            $tabNames[$lang['locale']] = $this->trans('Ranking', [], 'Modules.Cwms_ranking.Admin', $lang['locale']);
        }
        $this->tabs = [
            [
                'route_name' => $routeName,
                'class_name' => $className,
                'visible' => true,
                'name' => $tabNames,
                'parent_class_name' => 'AdminCatalog',
            ],
        ];
    }

    /**
     * function install
     * @return bool
     */
    public function install()
    {

        include(dirname(__FILE__) . '/sql/install.php');

        foreach (array_merge([
            'sales' => 'total_ranking',
            'position' => 'sales',
        ],self::REPLACES) as $key => $value){
            SrvCwmsRanking::replaceSortOrderField($key, $value);
        }
        return parent::install() && $this->registerHook('displayBackOfficeHeader') &&
            $this->registerHook('displayAdminProductsOptionsStepTop') &&
            $this->registerHook('actionManufacturerFormBuilderModifier') &&
            $this->registerHook('actionAfterCreateManufacturerFormHandler') &&
            $this->registerHook('actionAfterUpdateManufacturerFormHandler') &&
            $this->registerHook('actionProductUpdate');
    }

    /**
     * function uninstall
     * @return bool
     */
    public function uninstall()
    {
        include(dirname(__FILE__) . '/sql/uninstall.php');
        foreach (array_merge([
            'total_ranking' => 'saless',
            'sales' => 'position',
            'saless' => 'sales'
        ],self::REPLACES) as $key => $value){
            SrvCwmsRanking::replaceSortOrderField($key, $value);
        }
        return parent::uninstall() &&
            SrvCwmsRanking::replaceSortOrderField('total_ranking', 'sales') &&
            SrvCwmsRanking::replaceSortOrderField('Ranking', 'Best sellers');
    }

    /**
     * function getContent
     */
    public function getContent()
    {
        Tools::redirectAdmin($this->context->link->getAdminLink(self::TAB_CLASS_NAME));
    }

    /**
     * function hookDisplayBackOfficeHeader
     */
    public function hookDisplayBackOfficeHeader()
    {
        if (in_array($this->context->controller->controller_name, [
            self::TAB_CLASS_NAME,'AdminProducts'
        ])) {
            $this->context->controller->addJS([
                $this->getPathUri() . 'views/js/back.js',
            ]);

            Media::addJsDef([
                'message_error' => self::MESSAGE_ERROR
            ]);
        }


    }

    /**
     * @param $params
     * @return string
     */
    public function hookDisplayAdminProductsOptionsStepTop($params)
    {
        $this->smarty->assign([
                'bonus_ranking' => SrvCwmsRanking::getBonusRankingProduct($params['id_product'], $this->idShop),
                'notice_message' => self::MESSAGE_ERROR,
            ]
        );
        return $this->fetch("module:" . $this->name . "/views/templates/hook/display_input.tpl");
    }

    /**
     * @param $params
     * @throws Exception
     */
    public function hookActionProductUpdate($params)
    {
        $product = $params['product'];
        $bonusRanking = (int)Tools::getValue('bonus_ranking');
        if ($product instanceof Product && $bonusRanking <= 100 && $bonusRanking >= 0) {
            try {
                if (Db::getInstance()->update("product_shop",
                    ["bonus_ranking" => $bonusRanking], "id_shop=" . $this->idShop . " AND id_product=" . $product->id)) {
                    CrownMakers\CR\Services\SrvCwmsRanking::calculateRanking($product->id);
                }
            } catch (Exception $e) {
                $this->context->controller->errors[] = $e->getMessage();
            }
        }
    }

    /**
     * @param $params
     */
    public function hookActionManufacturerFormBuilderModifier($params)
    {
        /**@var FormBuilderInterface* */
        $form_builder = $params['form_builder'];
        $idManufacturer = intval($params['id']);
        $form_builder->add("bonus_ranking", IntegerType::class, [
            'label' => "Bonus Ranking",
            'required' => false,
            'help' => self::MESSAGE_ERROR
        ]);

        $manufacturer = Db::getInstance()->getRow("SELECT * FROM " . _DB_PREFIX_ . "manufacturer_shop WHERE id_shop = $this->idShop AND id_manufacturer=$idManufacturer");

        if ($manufacturer) {
            $params['data']['bonus_ranking'] = $manufacturer['bonus_ranking'];
        }

        $form_builder->setData($params['data']);
    }

    /**
     * @param $params
     */
    public function hookActionAfterCreateManufacturerFormHandler($params)
    {
        $this->hookActionAfterUpdateManufacturerFormHandler($params);
    }

    /**
     * @param $params
     * @throws PrestaShopException
     */
    public function hookActionAfterUpdateManufacturerFormHandler($params)
    {
        if (isset($params['id']) && $params['form_data']['bonus_ranking'] <= 100 && $params['form_data']['bonus_ranking'] >= 0) {
            try {
                Db::getInstance()->update("manufacturer_shop",
                    ["bonus_ranking" => $params['form_data']['bonus_ranking']], "id_shop = " . $this->idShop . " AND id_manufacturer=" . $params['id']);
              CrownMakers\CR\Services\SrvCwmsRanking::calculateRankingByManufacturer([
                  'id_manufacturer' => $params['id'],
                  'bonus_ranking_manufacturer' => $params['form_data']['bonus_ranking']
              ]);

            } catch (Exception $e) {
                $this->context->controller->errors[] = $e->getMessage();
            }
        } else {
            $this->getContainer()->get('session')->getFlashBag()->add('error', self::MESSAGE_ERROR);
        }
    }
}
