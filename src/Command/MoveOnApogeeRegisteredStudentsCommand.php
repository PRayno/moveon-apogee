<?php

namespace App\Command;

use App\Apogee\OpiBuilder;
use App\MoveOn\MoveOnWrapper;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class MoveOnApogeeRegisteredStudentsCommand extends Command
{
    protected static $defaultName = 'moveon:apogee:registered-students';
    private $moveOn;
    private $opiFieldName;
    private $studentNumberField;
    private $opiBuilder;

    /**
     * MoveOnApogeeRegisteredStudentsCommand constructor.
     * @param ParameterBagInterface $parameterBag
     * @param OpiBuilder $opiBuilder
     * @param MoveOnWrapper $moveOnWrapper
     */
    public function __construct(ParameterBagInterface $parameterBag, OpiBuilder $opiBuilder, MoveOnWrapper $moveOnWrapper)
    {
        $moveonApiParameters = $parameterBag->get("moveon");
        $this->opiFieldName = $moveonApiParameters["opiFieldName"];
        $this->studentNumberField = $moveonApiParameters["studentNumberField"];
        $this->opiBuilder = $opiBuilder;
        $this->moveOn = $moveOnWrapper;
        parent::__construct();
    }

    protected function configure()
    {
        $this->setDescription('Mise à jour des numéros des étudiants inscrits dans APOGEE');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return bool|int|null
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $opiFieldName = "person.".$this->opiFieldName;

        $io = new SymfonyStyle($input, $output);

        try {
            $data = $this->moveOn->moveOnApi->sendQuery(
                "person",
                "list",
                '{"filters":"{\"groupOp\":\"AND\",\"rules\":[{\"field\":\"'.$opiFieldName.'\",\"op\":\"nn\",\"data\":\"\"},{\"field\":\"person.'.$this->studentNumberField.'\",\"op\":\"nu\",\"data\":\"\"}]}","visibleColumns":"person.id;'.$opiFieldName.'","locale":"eng","sord":"asc","sortName":"person.id","sortOrder":"asc","_search":"true","page":"1","rows":"100"}'
            );

            if (count($data->rows)==0)
            {
                $io->note("Aucun nouvel inscrit dans APOGEE");
                return true;
            }
        }
        catch (\Exception $exception) {
            $io->error($exception->getMessage());
            return false;
        }



        $idField =  "person.id";
        $count=0;
        if (!is_array($data->rows))
            $students = array($data->rows);
        else
            $students = $data->rows;

        foreach ($students as $student)
        {
            try {
                $studentNumber = $this->opiBuilder->findStudentNumber($student->$opiFieldName->__toString());
                if (!is_null($studentNumber))
                {
                    $this->moveOn->moveOnApi->save("person",["id"=>$student->$idField->__toString(),$this->studentNumberField=>$studentNumber]);
                    $count++;
                }
            }
            catch (\Exception $exception)
            {
                $io->error($exception->getMessage());
            }
        }

        $io->success("$count étudiant(s) ont été inscrits dans APOGEE");
    }
}
