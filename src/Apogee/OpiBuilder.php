<?php
/**
 * Created by PhpStorm.
 * User: piraynau
 * Date: 07/03/19
 * Time: 15:08
 */

namespace App\Apogee;


use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class OpiBuilder
{
    private $urlApogee;
    private $opi;
    private $countries;

    public function __construct(ParameterBagInterface $parameterBag, Opi $opi)
    {
        $this->urlApogee = $parameterBag->get("apogee")["service_url"];
        $this->countries = $parameterBag->get("countries");
        $this->opi = $opi;
    }

    /**
     * @param $name
     * @param $value
     */
    public function set($name, $value)
    {
        $this->opi->$name = $this->transcode($name,$value);
    }

    /**
     * @param $field
     * @param $value
     * @return string
     */
    protected function transcode($field,$value)
    {
        switch ($field)
        {
            case "individu|donneesNaissance|dateNaiIndOpi" :
                $dob=explode("-",$value);
                return $dob[2].$dob[1].$dob[0];
                break;

            case "individu|donneesNaissance|codDepPayNai" :
            case "adresseFixe|codPay" :
                foreach ($this->countries as $country)
                {
                    if ($country["moveon_name"] === $value)
                        return $country["apogee_code"];
                }
                break;

            case "individu|donneesNaissance|codPayNat" :
            case "individu|dernierDiplObt|codDepPayDerDip":
            case "individu|dernierEtbFrequente|codDepPayAntIaaOpi":
            case "individu|situationAnnPre|codDepPayAnnPreOpi":
                return $this->countries[$value]["apogee_code"];
                break;

            case "individu|etatCivil|codSexEtuOpi" :
                if ($value === "Féminin")
                    return "F";
                else
                    return "M";
                break;

            case "adresseFixe|libAde":
                $postCodeCity = $this->opi->tempPostCode." ".$value;
                unset($this->opi->tempPostCode);
                return $postCodeCity;
                break;

            default:
                return $value;
        }
    }

    /**
     * @return mixed
     * @throws \Exception
     */
    public function publish()
    {
        $opi = ["_donneesOpi" => $this->explodeTree(get_object_vars($this->opi),"|")];

        try {
            $apogee = new \SoapClient($this->urlApogee."OpiMetier?wsdl");
            return $apogee->__soapCall('mettreajourDonneesOpi_v7',[$opi]);
        }
        catch (\SoapFault $fault)
        {
            throw new \Exception($fault->getMessage());
        }
    }

    /**
     * @param $array
     * @param string $delimiter
     * @param bool $baseval
     * @return array|bool
     */
    public function explodeTree($array, $delimiter = '_', $baseval = false)
    {
        if(!is_array($array)) return false;
        $splitRE   = '/' . preg_quote($delimiter, '/') . '/';
        $returnArr = array();
        foreach ($array as $key => $val) {
            // Get parent parts and the current leaf
            $parts	= preg_split($splitRE, $key, -1, PREG_SPLIT_NO_EMPTY);
            $leafPart = array_pop($parts);

            // Build parent structure
            // Might be slow for really deep and large structures
            $parentArr = &$returnArr;
            foreach ($parts as $part) {
                if (!isset($parentArr[$part])) {
                    $parentArr[$part] = array();
                } elseif (!is_array($parentArr[$part])) {
                    if ($baseval) {
                        $parentArr[$part] = array('__base_val' => $parentArr[$part]);
                    } else {
                        $parentArr[$part] = array();
                    }
                }
                $parentArr = &$parentArr[$part];
            }

            // Add the final part to the structure
            if (empty($parentArr[$leafPart])) {
                $parentArr[$leafPart] = $val;
            } elseif ($baseval && is_array($parentArr[$leafPart])) {
                $parentArr[$leafPart]['__base_val'] = $val;
            }
        }
        return $returnArr;
    }

    /**
     * @param string $opiNumber
     * @return |null
     * @throws \Exception
     */
    public function findStudentNumber(string $opiNumber)
    {
        $arguments = [
            '_codEtu'=>'',
            '_numINE'=>'',
            '_codOPI'=>$opiNumber,
            '_nom'=>'',
            '_prenom'=>'',
            '_dateNaiss'=>'',
            '_numBoursier'=>'',
            '_temoinRecupAnnu'=>'',
            '_codInd' => ''
        ];

        try {
            $apo = new \SoapClient($this->urlApogee."EtudiantMetier?wsdl");
            $result = $apo->__soapCall('recupererIdentifiantsEtudiant_v2',[$arguments]);
            return $result->recupererIdentifiantsEtudiant_v2Return->codEtu;
        }
        catch (\SoapFault $fault) {
            if ($fault->getMessage() == "technical.data.nullretrieve.etudiant")
                return null;

            throw new \Exception($fault->getMessage());
        }
    }

    /**
     * @param $moveOnId
     * @return string
     */
    public function generateOpiNumber($moveOnId)
    {
        $number = "M".(date("m")>=7 ? date("y"):date("y")-1);
        for ($i=strlen($moveOnId);$i<7;$i++)
        {
            $number.="0";
        }

        return $number.$moveOnId;
    }
}