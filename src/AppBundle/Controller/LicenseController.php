<?php

namespace AppBundle\Controller;

use AppBundle\Entity\License;
use AppBundle\Form\LicenseFilterType;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;

class LicenseController extends Controller
{
    /**
     * @Route("/licenses", name="licenses")
     */
    public function indexAction(Request $request)
    {
        $filters = [
            'limit' => 50,
            'sort_field' => 'startDate',
            'sort_direction' => 'DESC'
        ];

        $repository = $this->getDoctrine()->getRepository('AppBundle:License');
        $addonChoices = $repository->getAddonChoices();

        $filterForm = $this->createForm(new LicenseFilterType($addonChoices), $filters);
        $filterForm->handleRequest($request);
        $filters = $filterForm->getData();

        $licenses = $repository->findFiltered($filters);
        return $this->render(':license:list.html.twig', [
            'licenses' => $licenses,
            'filterForm' => $filterForm->createView()
        ]);
    }

    /**
     * @Route("/license/{licenseId}", name="license_detail")
     */
    public function detailAction(Request $request, $licenseId)
    {
        $licenses = $this->getDoctrine()->getRepository('AppBundle:License')
            ->findBy(['licenseId' => $licenseId]);

        $registeredDrills = $this->getDoctrine()->getRepository('AppBundle:DrillRegisteredSchema')
            ->findBy(['licenseId' => $licenseId]);

        $sales = $this->getDoctrine()->getRepository('AppBundle:Sale')
            ->findBy(['licenseId' => $licenseId]);

        return $this->render(':license:detail.html.twig', [
            'licenses' => $licenses,
            'sales' => $sales,
            'registeredDrills' => $registeredDrills
        ]);
    }
}
