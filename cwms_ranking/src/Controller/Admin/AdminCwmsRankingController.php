<?php
declare(strict_types=1);

namespace CrownMakers\CR\Controller\Admin;

use PrestaShopBundle\Controller\Admin\FrameworkBundleAdminController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Class AdminCwmsRankingController
 * @package CrownMakers\CR\Controller\Admin
 */
class AdminCwmsRankingController extends FrameworkBundleAdminController
{
    /**
     * @return Response|null
     */
    public function indexAction()
    {
        return $this->render('@Modules/cwms_ranking/views/templates/admin/configure.html.twig', [
            'stock' => $this->getConfig('PONDERATION_STOCK'),
            'product' => $this->getConfig('PONDERATION_PRODUCT'),
            'manufacturer' => $this->getConfig('PONDERATION_MANUFACTURER'),
            'ponderation_values' => [0,1,2,3,4,5]
        ]);
    }

    /**
     * @param Request $request
     * @return RedirectResponse
     */
    public function saveAction(Request $request)
    {
        $data = $request->request->all();
        if (\Tools::isSubmit('save_config_cwms_ranking')) {
           $this->setConfig('PONDERATION_STOCK', $data['ponderation_stock']);
           $this->setConfig('PONDERATION_PRODUCT', $data['ponderation_product']);
           $this->setConfig('PONDERATION_MANUFACTURER', $data['ponderation_manufacturer']);
           $this->addFlash('success', $this->trans('Update successfully  !', 'Modules.Cwms_ranking.Admin'));
        }
        return $this->redirectToRoute('cwms_ranking_index', ['status' => true]);
    }

    /**
     * @param $value
     * @return false|string
     */
    private function getConfig($value)
    {
        return \Configuration::get($value);
    }

    /**
     * @param $key
     * @param $value
     * @return bool
     */
    private function setConfig($key, $value)
    {
        return \Configuration::updateValue($key, $value);
    }
}
