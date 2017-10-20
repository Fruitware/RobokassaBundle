<?php
namespace Fruitware\RobokassaBundle\Client;

class Auth
{
    /** @var string */
    private $password1;

    /** @var string */
    private $password2;


    public function __construct($password1, $password2)
    {
        $this->password1 = $password1;
        $this->password2 = $password2;
    }

    public function sign($login, $outSum, $invoiceId)
    {
        return md5($login . ':' . $outSum . ':' . $invoiceId . ':' . $this->password1);
    }

    public function signXML($login, $invoiceId)
    {
        return md5($login . ':' . $invoiceId . ':' . $this->password2);
    }


    public function validateResult($sign, $outSum, $invoiceId)
    {
        $crc = md5($outSum . ':' . $invoiceId . ':' . $this->password2);
        return strtoupper($sign) === strtoupper($crc);
    }

    public function validateSuccess($sign, $outSum, $invoiceId)
    {
        $crc = md5($outSum . ':' . $invoiceId . ':' . $this->password1);
        return strtoupper($sign) === strtoupper($crc);
    }
}