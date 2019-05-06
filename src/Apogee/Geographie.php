<?php
namespace App\Apogee;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class Geographie
{
    private $urlApogee;

    public function __construct(ParameterBagInterface $parameterBag)
    {
        $this->urlApogee = $parameterBag->get("apogee")["service_url"];
    }

    public function listePays($codePays="",$enService="O")
    {
        try {
            $apogee = new \SoapClient($this->urlApogee."GeographieMetier?wsdl");
            return $apogee->__soapCall('recupererPays',["_codePays"=>$codePays,"_temoinEnService"=>$enService]);
        }
        catch (\SoapFault $fault)
        {
            throw new \Exception($fault->getMessage());
        }
    }
}