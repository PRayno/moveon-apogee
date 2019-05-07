# Import MoveOn incoming students to APOGEE

MoveOn (https://www.qs-unisolution.com/moveon/) is an application used to manage International Mobility between universities and schools (eg. Erasmus program) and APOGEE is the main student management software in French Universities.

**NOTE : As this library will only be used by French speakers, the documentation will be written in French.**

## Description

Cette librairie est basée sur Symfony et propose un ensemble de commandes en CLI permettant de :
- Importer des étudiants entrants de MoveOn dans APOGEE
- Mettre à jour les étudiants entrants de MoveOn avec leur numéro de dossier de l'établissement
- Gérer le référentiel des pays MoveON / APOGEE

Le process est le suivant :
1. Récupération des informations de la base MoveOn
2. Transformation de ces données pour être interprétées par APOGEE
3. Création d'une OPI dans la base APOGEE
4. Insertion du numéro OPI dans un champ de la base MoveON
5. Inscription administrative par un opérateur APOGEE en utilisant le numéro OPI pour récupérer les données
6. Insertion du numéro de dossier APOGEE dans un champ de la base MoveOn

**Avant toute chose, les API de votre installation MoveOn doivent être activées par le support de QS et le certificat X509 configuré (voir librarie https://github.com/PRayno/moveon)**

### Configuration préalable

Afin de fonctionner cet outil a besoin de 3 fichiers JSON décrivant la configuration.

- Transcription des champs MoveON / Apogee : transcription des champs MoveON (liste des champs dans URL_ENTITIES) vers les champs APOGEE (chaque niveau est séparé par un |). Exemple :

```json
{
  "person.surname" :"individu|etatCivil|libNomPatIndOpi",
  "person.first_name":"individu|etatCivil|libPr1IndOpi",
  "person.date_of_birth":"individu|donneesNaissance|dateNaiIndOpi",
  "person.email":"individu|donneesPersonnelles|adrMailOpi",
  "person.country_of_birth.id": "individu|donneesNaissance|codDepPayNai",
  "person.nationality.id": "individu|donneesNaissance|codPayNat"
}
```


- Ajout d'informations supplémentaires : certaines informations nécessaires à APOGEE sont communes à tous les étudiants que l'on souhaite importer (par exemple le témoin de naissance estimé ou le voeu de l'OPI). Exemple :

```json
{
  "individu|donneesNaissance|temDateNaiRelOpi":"N",
  "individu|donneesNaissance|codTypDepPayNai": "P",
  "voeux": [{
    "codAttDec": "",
    "codCge": "CGE_APOGEE",
    "codCmp": "",
    "codDecVeu": "F",
    "codDemDos": "C",
    "codDip": "CODE_DIPLOME",
    "codEtp": "CODE_ETAPE",
    "codMfo": "",
    "codSpe1Opi": "",
    "codSpe2Opi": "",
    "codSpe3Opi": "",
    "codTyd": "",
    "codVrsVdi": "CODE_VDI",
    "codVrsVet": "CODE_VET",
    "convocation": {
      "datCvc": "",
      "dhhCvc": "",
      "dmnCvc": ""
    },
    "libCmtJur": "",
    "numCls": "01",
    "temValPsd": "",
    "titreAccesExterne": {
      "codDacOpi": "",
      "codDepPayDacOpi": "",
      "codEtbDacOpi": "",
      "codTpeDacOpi": "",
      "codTypDepPayDacOpi": "",
      "daaDacOpi": ""
    }
  }]
}
```

- Une liste de correspondance des pays. Par défaut, un fichier est disponible dans `src/Resources/countries.json`. 
Si cela ne correspond pas à la configuration de l'environnement MoveON / Apogee, il est possible d'invoquer une commande qui essaye de faire la correspondance entre les pays MoveOn et APOGEE (comparaison sur le nom, il faut vérifier et éditer le fichier pour certains pays) une fois la librairie installée :

```
bin/console  bin/console moveon:country-referential https://MON-INSTALLATION-MOVEON-bo.moveonfr.com/reference-list/get-reference-list/list/countries  /chemin/du/fichier/en/sortie/correspondance.json
```


## Installation

Récupération du code sur un serveur (PHP 7.2 - tout est en CLI, pas besoin de serveur web)

Installation des packages via composer :

`composer install`

Les paramètres de configuration de la librairie sont dans le fichier .env.local

```dotenv
### MoveOn parameters
MOVEON_SERVICE_URL='https://MOVEON-INSTANCE/restService/index.php'  # URL de l'instance d'API
MOVEON_CERTIFICATE_FILE='/path/to/certificate/certificate.crt'      # Certificat X509 du serveur
MOVEON_KEY_FILE='/path/to/key/file/certificate.pem'                 # Clé publique
MOVEON_CERTIFICATE_PASSWORD='Password'                              # Mot de passe pour décoder cette clé
MOVEON_OPI_FIELD='external_id'                                      # Champ moveon (entité "person") dans lequel on va stocker le numéro OPI
MOVEON_STUDENT_NUMBER_FIELD='matriculation_id'                      # Champ moveon (entité "person") dans lequel on va stocker le numéro de dossier (numéro étudiant)

### Apogee parameters
APOGEE_SERVICE_URL='http://APOGEE-WS-URL/services/'                 # URL des webservices APOGEE

### Transcoders
MOVEON_TO_APOGEE_FIELDS_FILE='/path/to/file.json'                   # Fichier de transcodage des champs MoveON => APOGEE
OPI_EXTRA_VALUES_FILE='/path/to/file.json'                          # Fichier de paramétrage des valeurs communes à tous les étudiants importés
COUNTRIES_FILE='src/Resources/countries.json'                       # Fichier de transcodage des pays (valeur du fichier par défaut)
```

## Utilisation
- La commande `bin/console moveon:apogee:opi-create` permet de récupérer les étudiants de la base MoveON et de créer leur OPI dans APOGEE.
Par défaut, il utilise les critères : "status_fra" : "Prévu" et "direction_fra" :"Entrants" pour chercher les étudiants à importer. 
Il est possible de passer un argument en JSON pour retrouver les séjours à importer. Ex : `bin/console moveon:apogee:opi-create '{"person_id":"12345"}'` ne va importer que l'étudiant avec l'id 12345 dans la base.

- La commande `bin/console moveon:apogee:registered-students` permete quant à elle d'ajouter dans MoveON le numéro de dossier des étudiants ayant une inscription administrative complète ; il se base sur les deux champs MOVEON_OPI_FIELD et MOVEON_STUDENT_NUMBER_FIELD définis dans le .env.local   