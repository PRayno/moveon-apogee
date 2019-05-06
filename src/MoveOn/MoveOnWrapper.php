<?php


namespace App\MoveOn;


use PRayno\MoveOnApi\MoveOn;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class MoveOnWrapper
{
    public $moveOnApi;

    public function __construct(ParameterBagInterface $parameterBag)
    {
        $moveonApiParameters = $parameterBag->get("moveon");
        $this->moveOnApi = new MoveOn($moveonApiParameters["service_url"],$moveonApiParameters["certificatePath"],$moveonApiParameters["keyFilePath"],$moveonApiParameters["certificatePassword"]);
    }

    /**
     * @param null $arguments
     * @return \SimpleXMLElement
     * @throws \Exception
     */
    public function retrieveStudents($arguments=null)
    {
        //$data = $moveOnApi->findBy("stay",["status_fra"=>"Prévu","direction_fra"=>"Entrants"]);

        return $this->moveOnApi->findBy("stay",["person_id"=>"21515"]);
    }
}