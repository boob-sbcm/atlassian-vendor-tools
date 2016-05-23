<?php

namespace AppBundle\Command;

use AppBundle\Entity\License;
use GuzzleHttp\Client;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ImportLicenseCommand extends ContainerAwareCommand
{
    /** @var InputInterface */
    private $input;
    /** @var OutputInterface */
    private $output;

    protected function configure()
    {
        $this
            ->setName('app:import:license')
            ->addArgument('file', InputArgument::OPTIONAL, 'Import from file. Overrides remote import');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->input = $input;
        $this->output = $output;

        $container = $this->getContainer();
        $mailChimp = $container->get('app.service.mailchimp')->setOutput($output);
        $em = $container->get('doctrine')->getManager();
        $repository = $container->get('doctrine')->getRepository('AppBundle:License');

        if ($input->getArgument('file')) {
            $csv = $this->getLocalFile();
        } else {
            $csv = $this->getRemoteFile();
        }

        unset($csv[0]);
        $readCnt = 0;
        $newCnt = 0;

        foreach ($csv as $row) {
            $row = trim($row);
            if (empty($row)) continue;

            $data = str_getcsv($row, ',');
            $license = $repository->findOrCreate($data[0], $data[3]);
            $license->setFromCSV($data);

            if (!$this->allowedForImport($license)) continue;

            $mailChimp->addToList($license);
            if ($license->isNew()) {
                $newCnt++;
            }
            $readCnt++;

            $em->persist($license);

            if (($readCnt % 100) == 0)
                $output->writeln(sprintf('Imported %s of %s licenses, %s new so far', $readCnt, count($csv), $newCnt));
        }

        $em->flush();

        $output->writeln(sprintf('Imported %s licenses', count($csv)));
    }

    private function getLocalFile()
    {
        $filePath = $this->input->getArgument('file');
        $content = file_get_contents($filePath);

        return str_getcsv($content, "\n");
    }

    /**
     * @codeCoverageIgnore
     */
    private function getRemoteFile()
    {
        $container = $this->getContainer();
        $urlTemplate = 'https://marketplace.atlassian.com/rest/1.0/vendors/%s/license/report';

        $vendorId = $container->getParameter('vendor_id');
        $login = $container->getParameter('vendor_email');
        $password = $container->getParameter('vendor_password');

        $url = sprintf($urlTemplate, $vendorId);

        try {
            $client = new Client();
            $response = $client->get($url, ['auth' => [$login, $password]]);
            $contents = $response->getBody()->getContents();

            return str_getcsv($contents, "\n");

        } catch (\Exception $e) {
            $this->output->writeln($e->getMessage());

            return [];
        }
    }

    /**
     * The use-case of filtered add-ons is when a vendor wants to share information only relevant to a certain add-on
     *
     * @param License $license
     *
     * @return bool
     */
    private function allowedForImport(License $license)
    {
        if ($this->getContainer()->getParameter('filter_addons_enabled')) {
            $allowedKeys = $this->getContainer()->getParameter('filter_addons');
            if (in_array($license->getAddonKey(), $allowedKeys)) {
                return true;
            }

            return false;
        }

        return true;
    }
}