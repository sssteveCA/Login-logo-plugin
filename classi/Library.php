<?php

require_once("Image.php");

define("LIBRARY_QUERYERROR","1");
define("LIBRARY_NORESULT","2");
define("LIBRARY_STATUSEDITERROR","3");
define("LIBRARY_IMAGEALREADYEXISTS","4");
define("LIBRARY_IMAGENOTEXISTS","5");
define("LIBRARY_IMAGEREMOVEERROR","6");
define("LIBRARY_COPYFAILED","7");
define("LIBRARY_INVALIDPATHS","8");
define("LIBRARY_DELETEFILERROR","9");
define("LIBRARY_OPENDIRERROR","10");


class Library{
    private $wpdb;
    private $table; //nome della tabella che contiene le immagini della libreria
    private $nImages; //numero di immagini nella libreria personale
    private $images; //array di oggetti che contiene le immagini inserite
    private $used; //immagine utilizzata nella pagina di login
    private $errno; //codice di errore
    private $error; //messaggio di errore
    private $query; //ultima query inviata
    private $queries; //lista di tutte le query inviate
    private static $Adest = ABSPATH.'wp-content/plugins/loginLogoMin/img/';
    private static $Rdest = '../img/';
    private static $RdestRoot = './img/';
    private static $logFile = ABSPATH.'/logLibrary.txt';
    private static $mimes = array('image/gif','image/jpeg','image/png');

    public static function getUrlDest(){
        $urlDest =  get_home_url().'/wp-content/plugins/loginLogoMin/img/';
        return $urlDest;
    }

    public function __construct($dati = array())
    {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->table = isset($dati['table']) ? $dati['table'] : $this->wpdb->prefix.'ll_library';
        if($this->checkTable() === false){
            throw new Exception("la tabella {$this->table} non esiste");
        }
        $this->nImages = $this->pGetCountImages();
        $this->images = $this->pGetImages();
        $this->used = $this->pGetUsedImage();
        $this->query = "";
        $this->queries = array();
        $this->errno = 0;
    }

    public function getErrno(){return $this->errno;}
    public function getError(){
        switch($this->errno){
            case 0:
                $this->error = null;
                break;
            case LIBRARY_QUERYERROR:
                $this->error = "Library => Query errata";
                break;
            case LIBRARY_NORESULT:
                $this->error = "Library => La ricerca non ha prodotto alcun risultato";
                break;
            case LIBRARY_STATUSEDITERROR:
                $this->error = "Library => Impossibile cambiare l'immagine di login";
                break;
            case LIBRARY_IMAGEALREADYEXISTS:
                $this->error = "Library => L'immagine che si vuole aggiungere esiste già";
                break;
            case LIBRARY_IMAGENOTEXISTS:
                $this->error = "Library => L'immagine indicata non è presente nella libreria";
                break;
            case LIBRARY_IMAGEREMOVEERROR:
                $this->error = "Library => Errore durante la rimozione dell'immagine";
                break;
            case LIBRARY_COPYFAILED:
                $this->error = "Library => Impossibile aggiungere l'immagine perché la copia è fallita";
                break;
            case LIBRARY_INVALIDPATHS:
                $this->error = "Library => Impossibile aggiungere l'immagine perché uno o più percorsi specificati non sono validi";
                break;
            case LIBRARY_DELETEFILERROR:
                $this->error = "Library => Errore durante la cancellazione del file";
                break;
            case LIBRARY_OPENDIRERROR:
                $this->error = "Library => Errore durante l'apertura della cartella delle immagini caricate dall'utente";
                break;
            default:
                $this->error = null;
                break;
        }
        return $this->error;
    }
    public function getQuery(){return $this->query;}
    public function getQueries(){return $this->queries;}
    public function getCountImages(){return $this->nImages;}
    public function getImages(){
        /*(Unisci le immagini caricate dall'utente con quelle della libreria) */
        $this->images = $this->pGetImages();
        return $this->images;
    }
    public function getUsedImage(){
        $this->used = $this->pGetUsedImage();
        return $this->used;
    }

    //controlla se la tabella $this->table esiste
    private function checkTable(){
        $exists = false;
        $q = <<<SQL
SHOW TABLES LIKE '%s';
SQL;
        $this->query = $this->wpdb->prepare($q,$this->table);
        if($this->wpdb->get_var($this->query) == $this->table){
            $exists = true; //la tabella esiste
        }
        $this->queries[] = $this->query;
        return $exists;
    }

    //controlla se esiste un'altra immagina con valore di 'used' uguale a '1'
    private function checkUsed(){
        $exists = null;
        $this->query = <<<SQL
SELECT * FROM `{$this->table}` WHERE `used` = 1;
SQL;
        $row = $this->wpdb->get_row($this->query,ARRAY_A);
        if($row !== null){
            $dati = array('id' => $row['id']);
            $exists = new Image($dati);
            $exists->getImageInfoById();
        }
        $this->queries[] = $this->query;
        return $exists;
    }

    //Aggiunge un'immagine alla libreria
    public function Add(Image $image,$source = '',$dest = ''){
        $ok = false;
        $this->errno = 0;
        /*se il file è stato copiato o è stata selezionata l'immagine dalla libreria*/
        $fileCopy = false; 
        //percorso dell'immagine da inserire nella libreria
        $src = $image->getSrc();
        $imgCheck = $this->getImageBySrc($src);
        //controllo che l'immagine da aggiungere non esista già nella libreria
        if($imgCheck === null){
            $fonte = $image->getFonte();
            //l'immagine è caricata dal pc dell'utente
            if($fonte == 0){
                if(file_exists($source) && file_exists(dirname($dest))){
                    //cartella percorso di destinazione
                    $dir = dirname($dest); 
                    //nome del file del percorso di destinazione
                    $base = basename($dest);
                    //se la cartella di destinazione non esiste la creo
                    if(!file_exists($dir)){
                        mkdir($dir);
                    }
                    $copy = copy($source,$dest);
                    if($copy){
                            $src = Library::getUrlDest().$base;  
                            $fileCopy = true;        
                    }//if($copy){   
                    else{
                        $this->errno = LIBRARY_COPYFAILED;
                    }
                }//if(file_exists($source) && file_exists($dest)){
                //in questo modo la funzione aggiunge la riga del file caricato dall'utente che ancora non esisteva nel database
                else if($source == '' && $dest == ''){
                    $fileCopy = true;
                }
                else{
                    $this->errno = LIBRARY_INVALIDPATHS;
                }
            }//if($fonte == '0'){
            else{
                $fileCopy = true;
            }
            if($fileCopy){
                $q = <<<SQL
INSERT INTO `{$this->table}` (`src`,`fonte`) VALUES ('%s',%d);
SQL;
                $this->query = $this->wpdb->prepare($q,$src,$fonte);
                $insert = $this->wpdb->query($this->query);
                //$insert = $this->wpdb->insert($this->table,array('src' => $image->getSrc()),array('%s'));
                //$this->query = $this->wpdb->last_query;
                $this->queries[] = $this->query;
                if($insert !== false){
                    //aggiorna la lista di immagini presenti nella libreria
                    $this->getImages();
                    $ok = true;
                }
                else{
                    $this->errno = LIBRARY_QUERYERROR;
                }
            }//if($fileCopy)
            else{
            }
        }
        else{
            $this->errno = LIBRARY_IMAGEALREADYEXISTS;
        }
        return $ok;
    }

    //Elimina un'immagine dalla libreria
    public function Delete(Image $image){
        $ok = false;
        $usedOk = true;
        $this->errno = 0;
        $id = $image->getId();
        //controlla che l'immagine esista nella tabella prima di proseguire
        $imgCheck = $this->getImageById($id);
        if($imgCheck !== null){
            //controlla se l'immagine è usata nella pagina login
            if($image->isUsed()){
                $image->setUsedStatus('0');
                if($image->getErrno() == 0){
                    $this->used = null;
                }
                else{
                    $this->errno = LIBRARY_IMAGEREMOVEERROR;
                    $usedOk = false;
                }
            }//if($image->isUsed())
            $filename = basename($image->getSrc());
            if($image->getFonte() == 0 && file_exists(Library::$Adest.$filename)){
                $canc = unlink(Library::$Adest.$filename);
                if(!$canc){
                    $usedOk = false;
                    $this->errno = LIBRARY_DELETEFILERROR;
                }
            }
            if($usedOk){
                $q = <<<SQL
DELETE FROM `{$this->table}` WHERE `id` = %d;
SQL;
                $this->query = $this->wpdb->prepare($q,$id);
                $delete = $this->wpdb->query($this->query);
                $this->queries[] = $this->query;
                //$delete = $this->wpdb->delete($this->table,array('id' => $id),array('%d'));
                if($delete !== false){
                    //aggiorna la lista di immagini presenti nella libreria
                    $this->getImages();
                    //se l'immagine era utilizzata come logo della pagina di login
                    $ok = true;
                }
                else{
                    $this->errno = LIBRARY_QUERYERROR;
                }
            }//if($usedOk)
            else{
                //$this->errno = LIBRARY_IMAGEREMOVEERROR;
            }
        }//if($imgCheck !== null)
        else{
            $this->errno = LIBRARY_IMAGENOTEXISTS;
        }
        return $ok;
    }

    //Ottiene l'immagine della libreria con l'id specificato
    public function getImageById($id){
        file_put_contents(Library::$logFile,"Library getImageById()\r\n",FILE_APPEND);
        $img = null;
        $this->errno = 0;
        $q = <<<SQL
SELECT * FROM `{$this->table}` WHERE `id` = %d;
SQL;
        $this->query = $this->wpdb->prepare($q,$id);
        $imgA =  $this->wpdb->get_row($this->query,ARRAY_A);
        if($imgA != null){
            if(sizeof($imgA) > 0){
                $dati = array();
                $dati['id'] = $id;
                $img = new Image($dati);
                $img->getImageInfoById();
            }
            else{
                $this->errno = LIBRARY_NORESULT;
            }
        }
        else{
            $this->errno = LIBRARY_QUERYERROR;
        }
        $this->queries[] = $this->query;
        return $img;
    }

    //Ottiene l'immagine della libreria con il sorgente specificato
    public function getImageBySrc($src){
        $img = null;
        $this->errno = 0;
        $q = <<<SQL
SELECT * FROM `{$this->table}` WHERE `src` = '%s';
SQL;
        $this->query = $this->wpdb->prepare($q,$src);
        $imgA =  $this->wpdb->get_row($this->query,ARRAY_A);
        if($imgA != null){
            if(sizeof($imgA) > 0){
                $dati = array();
                $dati['src'] = $src;
                $img = new Image($dati);
                $img->getImageInfoBySrc();
            }
            else{
                $this->errno = LIBRARY_NORESULT;
            }
        }
        else{
            $this->errno = LIBRARY_QUERYERROR;
        }
        $this->queries[] = $this->query;
        return $img;
    }

    //restitusce le immagini caricate dall'utente
    public function getUploadedImages(){
        $images = array();
        $this->errno = 0;
        if($this->readDir()){
            $this->query = <<<SQL
SELECT * FROM `{$this->table}` WHERE `fonte` = 0;
SQL;
            $imagesA = $this->wpdb->get_results($this->query,OBJECT_K);
            if($imagesA !== null){
                $i = 0;
                foreach($imagesA as $img){
                    $imgA = array(
                        'id' => $img->id,
                        'src' => $img->src,
                        'used' => $img->used,
                        'fonte' => $img->fonte
                    );
                    $images[$i] = new Image($imgA);
                    if($images[$i]->fileExists()){
                        $i++;     
                    }
                    else{
                        //rimuove le righe della tabella che contengono percorsi non validi
                        $this->Delete($images[$i]);
                    }    
                }//foreach($imagesA as $img){
            }//if($imagesA !== null){
            else{
                $this->errno = LIBRARY_QUERYERROR;
            }
        }//if($this->readDir())
        return $images;
    }

    //restituisce le immagini della libreria caricate da wordpress
    public function getWordpressLibImages(){
        $images = array();
        $this->errno = 0;
        $this->query = <<<SQL
SELECT * FROM `{$this->table}` WHERE `fonte` = 1;
SQL;
        $imagesA = $this->wpdb->get_results($this->query,OBJECT_K);
        if($imagesA !== null){
            $i = 0;
            foreach($imagesA as $img){
                $imgA = array(
                    'id' => $img->id,
                    'src' => $img->src,
                    'used' => $img->used,
                    'fonte' => $img->fonte
                );
                $images[$i] = new Image($imgA);
                if($images[$i]->fileExists()){
                    $i++;     
                }
                else{
                    $this->Delete($images[$i]);
                }
            }//foreach($imagesA as $img){
        }//if($imagesA !== null){
        else{
            $this->errno = LIBRARY_QUERYERROR;
        }
        return $images;
    }

    //Numero di immagini presenti nella libreria
    private function pGetCountImages(){
        $nImages = 0;
        $this->errno = 0;
        $this->query = <<<SQL
SELECT COUNT(`src`) FROM `{$this->table}`;
SQL;
        $n = $this->wpdb->get_var($this->query);
        if($n !== null){
            $nImages = $n;
        }
        else{
            $this->errno = LIBRARY_QUERYERROR;
        }
        $this->queries[] = $this->query;
        return $nImages;
    }

    //Lista delle immagini presenti nella libreria
    private function pGetImages(){
        $images = array();
        $this->errno = 0;
        $uploaded = $this->getUploadedImages();
        if($this->getErrno() == 0){
            $wordpressLib = $this->getWordpressLibImages();
            if($this->getErrno() == 0){
                $images = array_merge($uploaded,$wordpressLib);
            }
        }
        return $images;
    }

    //Ottiene l'Immagine utilizzata dalla pagina di login(privata)
    private function pGetUsedImage(){
        $img = null;
        $this->errno = 0;
        $this->query = <<<SQL
SELECT * FROM `{$this->table}` WHERE `used` = 1;
SQL;
        $imgA = $this->wpdb->get_row($this->query,ARRAY_A);
        if($imgA !== null){
            if(sizeof($imgA) > 0){
                $img = new Image($imgA);
                $img->getImageInfoById();
            }
            else{
                $this->errno = LIBRARY_NORESULT;
            }
        }
        else{
            $this->errno = LIBRARY_QUERYERROR;
        }  
        return $img;
    }

    //leggi la cartella con i file caricati dall'utente e modifica la tabella contenente le informazioni sui file
    private function readDir(){
        $ok = false;
        $loopOk = '0';
        $this->errno = 0;
        //se la cartella di destinazione non esiste la creo
        if(!file_exists(Library::$Adest)){
            mkdir(Library::$Adest);
        }
        $httpDir = Library::getUrlDest();
        //apro la cartella delle immagini caricate dall'utente
        $open = opendir(Library::$Adest);
        if($open !== false){
            $n = count(scandir(Library::$Adest));
            if($n < 3){
                $loopOk = '1';
            }
            else{
                while(($file = readdir($open)) !== false){
                    if($file != "." && $file != ".."){
                        //sostituisce gli spazi vuoti nei nomi dei file con '_'
                        $newFile = str_replace(" ","_",$file);
                        rename(Library::$Adest.$file,Library::$Adest.$newFile);
                        //controlla il tipo di file
                        $type = wp_check_filetype(Library::$Adest.$newFile);
                        //il file specificato è di tipo immagine
                        if(in_array($type['type'],Library::$mimes)){
                          $src = $httpDir.$newFile;  
                          //il file immagine esiste ma non è presente nella tabella    
                          if($this->getImageBySrc($src) == null){
                              $imageInfo = array(
                                  'src' => $src,
                                  'fonte' => 0
                              );
                              try{
                                  $image = new Image($imageInfo);
                                  $add = $this->Add($image);
                                  if(!$add){
                                      $loopOk = '-1';
                                      break;
                                  }
                                  else $loopOk = '1';
                              }
                              catch(Exception $e){
    
                              }
                          }//if($this->getImageBySrc($src) == null){   
                          else{
                              $loopOk = '1';
                          }
                        }//if(in_array($type['ext'],Library::$mimes)){
                    }//if($file != "." && $file != ".."){
                }//while(($file = readdir($open)) !== false){
            }//else di if($n < 3)
            if($loopOk == '1'){
                $ok = true;
            }
            else{
                //errori della funzione Add
            }
        }//if($open !== false){
        else{
            $this->errno = LIBRARY_OPENDIRERROR;
        }
        return $ok;
    }

    //modifica l'immagine della pagina di login
    public function setUsedImage(Image $image){
        $ok = false;
        $this->errno = 0;
        $setStatus = $image->setUsedStatus('1');
        if($setStatus){
            $this->used = $image;
            $ok = true;
        }
        else{
            $this->errno = LIBRARY_STATUSEDITERROR;
        }
        return $ok;
    }
}
?>