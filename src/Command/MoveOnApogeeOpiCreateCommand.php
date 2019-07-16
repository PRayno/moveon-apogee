<?php

namespace App\Command;

use App\Apogee\OpiBuilder;
use App\MoveOn\MoveOnWrapper;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;


class MoveOnApogeeOpiCreateCommand extends Command
{
    protected static $defaultName = 'moveon:apogee:opi-create';
    private $opiBuilder;
    private $moveOn;
    private $opiExtraValues;
    private $transcodedFields;
    private $transcodedCustomFieldsValues;
    private $customFieldsToTranscode;
    private $opiFieldName;
    private $opiToImportFieldName;

    /**
     * MoveOnApogeeOpiCreateCommand constructor.
     * @param OpiBuilder $opiBuilder
     * @param MoveOnWrapper $moveOn
     * @param ParameterBagInterface $parameterBag
     */
    public function __construct(OpiBuilder $opiBuilder, MoveOnWrapper $moveOn,ParameterBagInterface $parameterBag)
    {
        $this->opiBuilder = $opiBuilder;
        $this->moveOn = $moveOn;
        $this->opiExtraValues = $parameterBag->get("opi_extra_values");
        $this->transcodedFields = $parameterBag->get("transcoded_fields");
        $this->transcodedCustomFieldsValues = $parameterBag->get("transcoded_custom_fields");
        $this->customFieldsToTranscode = $parameterBag->get("custom_fields_to_transcode");
        $this->opiFieldName = $parameterBag->get("moveon")["opiFieldName"];
        $this->opiToImportFieldName = $parameterBag->get("moveon")["opiToImportFieldName"];
        parent::__construct();
    }


    protected function configure()
    {
        $this
            ->setDescription('Generate OPI from MoveON users')
            ->addArgument("students-query", InputArgument::OPTIONAL, "JSON of the criteria to retrieve students")
            ->addOption("dump", "d",InputOption::VALUE_NONE, 'Dump the content before registration')
        ;
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int|void|null
     * @throws \Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);
        $data = $this->moveOn->retrieveStudents(json_decode($input->getArgument("students-query"),true));

        $transcoding = $this->transcodedFields;
        $extraValues = $this->opiExtraValues;

        if (count($data->rows))
        {
            foreach ($data->rows as $stay)
            {
                $stay = (array) $stay;
                $columnsTemp = [];
                foreach (array_keys($this->transcodedFields) as $field)
                {
                    if (substr($field,0,6)==="custom")
                        $columnsTemp[] = $field;
                }

                $columns = array_merge($this->moveOn->moveOnApi->getEntity("person"),$columnsTemp);
                $person = $this->moveOn->moveOnApi->findBy("person",["id"=>$stay["stay.person_id"]],["id"=>"asc"],10000,1,$columns);
                $opiBuilder = $this->opiBuilder;
                $extraValues["individu|codOpiIntEpo"] = $opiBuilder->generateOpiNumber($stay["stay.person_id"]);
                $array = array_merge($extraValues,$stay,(array) $person->rows[0]);

                foreach ($array as $field=>$value)
                {
                    if (!isset($transcoding[$field]) && !isset($extraValues[$field]))
                        continue;

                    if (in_array($field,$this->customFieldsToTranscode))
                    {
                        if (isset($this->transcodedCustomFieldsValues[$value]))
                            $value = $this->transcodedCustomFieldsValues[$value];
                    }

                    $transcodedField = (isset($transcoding[$field]) ? $transcoding[$field] : $field);
                    try {
                        $opiBuilder->set($transcodedField,$value);
                    }
                    catch (\Exception $exception)
                    {
                        $io->error("Stay ".$stay["stay.id"]." : ".$exception->getMessage());
                    }
                }

                if ($input->getOption("dump")===true)
                {
                    $io->text(json_encode($opiBuilder->publish(true)));
                    continue;
                }

                try {
                    $opiBuilder->publish();
                    $this->moveOn->moveOnApi->save("person",["id"=>$stay["stay.person_id"],$this->opiFieldName=>$extraValues["individu|codOpiIntEpo"]]);

                    if (!empty($this->opiToImportFieldName))
                    $this->moveOn->moveOnApi->save("stay",["id"=>$stay["stay.id"],$this->opiToImportFieldName=>0]);

                    $io->success("OPI publiée dans APOGEE (".$extraValues["individu|codOpiIntEpo"].")");
                }
                catch (\Exception $exception)
                {
                    $io->error($stay["stay.person_id"]." ".$stay["stay.person.fullname"]." : ".$exception->getMessage());
                }
            }
        }
    }
}