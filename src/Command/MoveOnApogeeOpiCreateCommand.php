<?php

namespace App\Command;

use App\Apogee\OpiBuilder;
use App\MoveOn\MoveOnWrapper;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
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
    private $opiFieldName;

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
        $this->opiFieldName = $parameterBag->get("moveon")["opiFieldName"];
        parent::__construct();
    }


    protected function configure()
    {
        $this->setDescription('Generate OPI from MoveON users');
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
        $data = $this->moveOn->retrieveStudents();

        $transcoding = $this->transcodedFields;
        $extraValues = $this->opiExtraValues;

        if (count($data->rows))
        {
            foreach ($data->rows as $stay)
            {
                $stay = (array) $stay;
                $person = $this->moveOn->moveOnApi->findBy("person",["id"=>$stay["stay.person_id"]]);

                $opiBuilder = $this->opiBuilder;
                $extraValues["individu|codOpiIntEpo"] = $opiBuilder->generateOpiNumber($stay["stay.person_id"]);
                $array = array_merge($extraValues,$stay,(array) $person->rows[0]);

                foreach ($array as $field=>$value)
                {
                    if (!isset($transcoding[$field]) && !isset($extraValues[$field]))
                        continue;

                    $transcodedField = (isset($transcoding[$field]) ? $transcoding[$field] : $field);
                    $opiBuilder->set($transcodedField,$value);
                }

                try {
                    $opiBuilder->publish();
                    $this->moveOn->moveOnApi->save("person",["id"=>$stay["stay.person_id"],$this->opiFieldName=>$extraValues["individu|codOpiIntEpo"]]);
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