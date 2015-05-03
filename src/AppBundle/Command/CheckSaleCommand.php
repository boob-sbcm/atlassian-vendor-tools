<?php

namespace AppBundle\Command;

use AppBundle\Entity\LastSale;
use GuzzleHttp\Client;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Doctrine\Common\Persistence\ObjectManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

class CheckSaleCommand extends ContainerAwareCommand
{
    /** @var ContainerInterface */
    private $container;
    private $vendorId;
    private $login;
    private $password;
    private $url;
    /** @var ObjectManager */
    private $em;

    protected function configure()
    {
        $this->setName('app:sale:check');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->init();
        $repository = $this->em->getRepository('AppBundle:LastSale');

        try {
            $json = $this->getLastSale();
            $lastMarketplaceSale = new LastSale();
            $lastMarketplaceSale->setFromJSON($json);

            $lastSale = $repository->findOneBy([]);
            if (!$lastSale) {
                $lastSale = $lastMarketplaceSale;
            } else {
                if ($this->isNewSale($lastSale, $lastMarketplaceSale)) {
                    $lastSale->setFromJSON($json);
                    $this->sendEmail($lastSale);
                    $output->writeln('New sale! DING DING!!');
                }
            }

            $this->em->persist($lastSale);
            $this->em->flush();

        } catch (\Exception $e) {
            $output->writeln($e->getMessage());

            return;
        }

        $this->em->flush();

        $output->writeln('Done');
    }

    private function sendEmail(LastSale $sale)
    {
        $mandrill = $this->container->get('app.mandrill');
        $message = [
            'html' => $this->getHTML($sale),
            'subject' => 'MPCRM - New Sale!',
            'from_email' => $this->login,
            'to' => [['email' => $this->login]]
        ];

        $mandrill->messages->send($message, true);
    }

    private function getHTML(LastSale $sale)
    {
        $html = '<h1>Congrats!</h1>';
        $html .= '<p>Yet another license has been sold for <strong>$%s</strong></p>';

        return sprintf($html, $sale->getVendorAmount());
    }

    private function isNewSale(LastSale $localSale, LastSale $apiSale)
    {
        return !($localSale->getDate() == $apiSale->getDate() && $localSale->getLicenseId() == $apiSale->getLicenseId());
    }

    private function getLastSale()
    {
        $client = new Client();
        $response = $client->get(
            $this->url,
            [
                'auth' => [$this->login, $this->password],
                'query' => ['limit' => 1]
            ]
        );

        $json = $response->json();

        return array_pop($json['sales']);
    }

    private function init()
    {
        $this->container = $this->getContainer();

        $this->vendorId = $this->container->getParameter('vendor_id');
        $this->login = $this->container->getParameter('vendor_email');
        $this->password = $this->container->getParameter('vendor_password');
        $this->em = $this->container->get('doctrine')->getManager();

        $urlTemplate = 'https://marketplace.atlassian.com/rest/1.0/vendors/%s/sales';
        $this->url = sprintf($urlTemplate, $this->vendorId);
    }
}