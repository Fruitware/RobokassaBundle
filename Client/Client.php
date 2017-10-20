<?php

namespace Fruitware\RobokassaBundle\Client;

use GuzzleHttp\Client as Guzzle;

use JMS\Payment\CoreBundle\Model\ExtendedDataInterface;
use JMS\Payment\CoreBundle\Model\FinancialTransactionInterface;
use JMS\Payment\CoreBundle\Plugin\Exception\BlockedException;

class Client
{
    /**
     * @var Auth
     */
    private $auth;

    /**
     * @var string
     */
    private $login;

    /**
     * @var bool
     */
    private $test;

    /**
     * Client constructor.
     *
     * @param Auth $auth
     * @param string $login
     * @param string $test
     */
    public function __construct(Auth $auth, $login, $test)
    {
        $this->auth = $auth;
        $this->login = $login;
        $this->test = $test;
    }

    /**
     * @return string
     */
    private function getWebServerUrl()
    {
        return 'https://auth.robokassa.ru/Merchant/Index.aspx';
    }

    /**
     * @return string
     */
    private function getXmlServerUrl()
    {
        return 'https://auth.robokassa.ru/Merchant/WebService/Service.asmx';
    }

    /**
     * @param FinancialTransactionInterface $transaction
     *
     * @return string
     */
    public function getRedirectUrl(FinancialTransactionInterface $transaction)
    {
        $invoiceId = $transaction->getPayment()->getPaymentInstruction()->getId();
        /** @var ExtendedDataInterface $data */
        $data = $transaction->getExtendedData();
        $data->set('invoiceId', $invoiceId);

        $description = 'test desc';
        if($data->has('description')) {
            $description = $data->get('description');
        }

        $parameters = [
            'MrchLogin' => $this->login,
            'OutSum' => $transaction->getRequestedAmount(),
            'InvId' => $invoiceId,
            'Desc' => $description,
            'IncCurrLabel' => '',
            'IsTest' => $this->test ? 1 : 0,
            'Signature' => $this->auth->sign($this->login, $transaction->getRequestedAmount(), $invoiceId),
        ];

        return $this->getWebServerUrl() .'?' . http_build_query($parameters);
    }

    /**
     * @param string $uri
     * @param array $parameters
     *
     * @return \Psr\Http\Message\ResponseInterface
     */
    private function post($uri, array $parameters = [])
    {
        $guzzle  = new Guzzle();
        return $guzzle->post($uri, ['form_params' => $parameters]);
    }

    /**
     * @param string $url
     * @param array $params
     *
     * @return \SimpleXMLElement
     * @throws BlockedException
     */
    private function sendXMLRequest($url, array $params = [])
    {
        $url = sprintf('%s?%s', $url, http_build_query($params));
        $response = $this->post($url, $params);
        $xml = new \SimpleXMLElement($response->getBody()->getContents());
        $result_code = (int)$xml->Result->Code;
        if ($result_code !== 0) {
            throw new BlockedException($xml->Result->Description);
        }
        return $xml;
    }

    /**
     * @param int $invoiceId
     *
     * @return int
     */
    public function requestOpState($invoiceId)
    {
        $params = [
            'MerchantLogin' => $this->login,
            'InvoiceID' => $invoiceId,
            'IsTest' => $this->test ? 1 : 0,
            'Signature' => $this->auth->signXML($this->login, $invoiceId),
        ];
//        if ($this->test) {
//            $params['StateCode'] = 100;
//        }

        $url = $this->getXmlServerUrl() . '/' . 'OpState';
        $result = $this->sendXMLRequest($url, $params);
        return (int)$result->State->Code;
    }
}