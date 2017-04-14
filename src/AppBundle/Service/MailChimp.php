<?php

namespace AppBundle\Service;

use AppBundle\Entity\License;
use GuzzleHttp\Client;
use Exception;
use GuzzleHttp\Exception\ClientException;
use Symfony\Component\Console\Output\OutputInterface;

class MailChimp
{
    private $apiKey;
    private $lists;
    private $dc;
    private $enabled;

    /**
     * @var OutputInterface
     */
    private $output;

    public function __construct($apiKey, $lists, $dc, $enabled)
    {
        $this->lists = $lists;
        $this->dc = $dc;
        $this->apiKey = $apiKey;
        $this->enabled = $enabled;
    }

    /**
     * Adds new subscriber to the mailchimp list
     *
     * Works only if the license is new (checks for the empty id)
     *
     * @param License $license
     */
    public function addToList(License $license)
    {
        if (!$this->enabled || !$license->isNew() && $license->getCompany()->getTechnicalContactEmail()) {
            // TODO: Not sure what to do, if we do not have technical contact email?
            return;
        }

        foreach ($this->lists as $list) {
            if ($license->getAddon()->getAddonKey() == $list['addon_key']) {
                $this->add($list['list_id'], $license);
            }
        }
    }

    private function add($listId, License $license)
    {
        // TODO: check, how will mailchimp handle already subscribed emails
        $body = [
            'email_address' => $license->getCompany()->getTechnicalContactEmail(),
            'status' => 'subscribed',
            'merge_fields' => [
                'FNAME' => $license->getCompany()->getTechnicalContactName()
            ]
        ];
        $headers = [
            'auth' => ['', $this->apiKey],
            'body' => json_encode($body)
        ];

        $url = 'https://' . $this->dc . '.api.mailchimp.com/3.0/lists/' . $listId . '/members';

        try {
            $client = new Client();
            $client->post($url, $headers);

            $this->output->writeln('Added '.$body["email_address"].' to mailchimp list '.$listId);
        } catch (ClientException $e) {
            $err = $e->getMessage();
            if ($e->hasResponse()) {
                $err = $e->getResponse()->getBody();
            }
            $this->output->writeln('Failed to add '.$body["email_address"].' to mailchimp list '.$listId.' due to error: '.$err);
        }
    }

    /**
     * @param OutputInterface $output
     * @return $this
     */
    public function setOutput(OutputInterface $output)
    {
        $this->output = $output;

        return $this;
    }
}