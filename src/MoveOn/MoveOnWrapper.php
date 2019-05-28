<?php
namespace App\MoveOn;


use PRayno\MoveOnApi\MoveOn;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class MoveOnWrapper
{
    public $moveOnApi;
    protected $importOpiField;

    public function __construct(ParameterBagInterface $parameterBag)
    {
        $moveonApiParameters = $parameterBag->get("moveon");
        $this->moveOnApi = new MoveOn($moveonApiParameters["service_url"],$moveonApiParameters["certificatePath"],$moveonApiParameters["keyFilePath"],$moveonApiParameters["certificatePassword"]);
        $this->importOpiField = $moveonApiParameters["opiToImportFieldName"];
    }

    /**
     * @param array $arguments
     * @return \SimpleXMLElement
     * @throws \Exception
     */
    public function retrieveStudents($arguments=[])
    {
        if (empty($arguments))
            $arguments = [$this->importOpiField=>"1"];

        return $this->moveOnApi->findBy("stay",$arguments);
    }
}