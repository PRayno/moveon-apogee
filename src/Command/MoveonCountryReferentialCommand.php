<?php

namespace App\Command;

use App\Apogee\Geographie;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class MoveonCountryReferentialCommand extends Command
{
    protected static $defaultName = 'moveon:country-referential';
    private $apogee;

    public function __construct(Geographie $apogee)
    {
        $this->apogee = $apogee;
        parent::__construct();
    }

    protected function configure()
    {
        $this
            ->setDescription('Build referential for countries')
            ->addArgument('moveon-countries', InputArgument::OPTIONAL, 'Moveon countries list reference url (https://my-moveon-bo-instance/reference-list/get-reference-list/list/countries)')
            ->addArgument('output-file', InputArgument::OPTIONAL, 'Output file location')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);

        try {
            $apogeeCountriesList = $this->apogee->listePays("101");
        }
        catch (\Exception $exception)
        {
            $io->error("Cannot retrieve countries from Apogee : ".$exception->getMessage());
            return false;
        }

        $countries=[];
        $json = json_decode(file_get_contents($input->getArgument("moveon-countries")));

        foreach ($json->items as $moveOnCountry) {
            if (empty($moveOnCountry->key))
                continue;

            $country = (object) ["moveon_id"=>$moveOnCountry->key,"moveon_name"=>$moveOnCountry->name,"apogee_code"=>"","apogee_name"=>""];
            $bestMatch=["percentage"=>0,"code"=>null,"name"=>null];
            foreach ($apogeeCountriesList->recupererPaysReturn as $apogeeCountry)
            {
                $sim = similar_text(strtoupper($country->moveon_name),$apogeeCountry->libPay,$perc);
                if ($perc > $bestMatch["percentage"])
                    $bestMatch=["percentage"=>$perc,"code"=>$apogeeCountry->codePay,"name"=>$apogeeCountry->libPay];
            }

            if ($bestMatch["percentage"] > 60)
            {
                $country->apogee_code = $bestMatch["code"];
                $country->apogee_name = $bestMatch["name"];
            }
            else
                $io->warning("Could not find a match for $country->moveon_name");

            $countries[$country->moveon_id] = $country;
        }

        file_put_contents($input->getArgument("output-file"),json_encode($countries));
    }
}
