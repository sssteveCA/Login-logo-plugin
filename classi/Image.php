<?php

define("IMAGE_NOTINSERTED", "1");
define("IMAGE_QUERYERROR", "2");
define("IMAGE_NOTDELETED", "3");
define("IMAGE_NORESULT","4");
define("IMAGE_NOTAFFECTED","5");
define("IMAGE_INVALIDSTATUS","6");
define("IMAGE_CHANGESTATUSERROR","7");
define("IMAGE_IDNOTEXISTS","8");
define("IMAGE_SRCNOTEXISTS","9");
define("IMAGE_INVALIDFORMAT","10");

class Image{
    private $wpdb;
    private $table;
    private $id; //ID dell'immagine
    private $src; //percorso dell'immagine
    private $errno; //codice di errore
    private $error; //messaggio di errore
    private $query; //ultima query inviata
    private $queries; //lista di tutte le query inviate
    private $used; //se l'immagine è utilizzata nella pagina di login
    private $fonte; //0 = immagine caricata dal PC, 1 = immagine caricata dalla libreria
    private static $mimes = array('image/gif','image/jpeg','image/png'); //tipi MIME accettati
    private static $logFile = ABSPATH.'/logLibrary.txt';

    public function __construct($dati)
    {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->table = isset($dati['table']) ? $dati['table'] : $this->wpdb->prefix.'ll_library';
        if($this->checkTable() === false){
            throw new Exception("la tabella {$this->table} non esiste");
        }
        $this->id = isset($dati['id']) ? $dati['id'] : null;
        $this->src = isset($dati['src']) ? $dati['src'] : null;
        $this->used = isset($dati['used']) ? $dati['used'] : '0';
        $this->fonte = isset($dati['fonte']) ? $dati['fonte'] : '0';
        $this->query = "";
        $this->queries = array();
        $this->errno = 0;
    }

    /*public function fileExists(){
        file_put_contents(Image::$logFile,"Image fileExists\r\n",FILE_APPEND);
        $this->errno = 0;
        $exists = false;
        if(isset($this->src)){
            file_put_contents(Image::$logFile,"Image fileExists isset src => {$this->src}\r\n",FILE_APPEND);
            $filename = $this->src;
            //$file_headers = @get_headers($filename);
            file_put_contents(Image::$logFile,"prima di file headers\r\n",FILE_APPEND);
            $file_headers = @get_headers($filename);
            file_put_contents(Image::$logFile,"dopo file headers\r\n",FILE_APPEND);
            file_put_contents(Image::$logFile,var_export($file_headers,true)."\r\n",FILE_APPEND);
            $regex = '/^HTTP\/[\d.]+\s200 OK$/i';
            //file_put_contents(Image::$logFile,var_export($file_headers,true)."\r\n",FILE_APPEND);
            if(preg_match($regex,$file_headers[0])){
                file_put_contents(Image::$logFile,"Image fileExists preg match true\r\n",FILE_APPEND);
                $exists = true;
            } else {
                file_put_contents(Image::$logFile,"Image fileExists preg match false\r\n",FILE_APPEND);
                $exists = false;
            }
        }
        else{
            file_put_contents(Image::$logFile,"Image fileExists not isset src\r\n",FILE_APPEND);
            $this->errno = IMAGE_SRCNOTEXISTS;
        }
        return $exists;
    }*/

    public function fileExists(){
        $exists = false;
        $src = $this->src;
        //posizione di 'wp-content' in this->src
        $pos = strpos($src,'wp-content/');
        //parte della stringa src che comincia con 'wp-content/'
        $substr1 = substr($src,$pos);
        //percorso immagine nel disco
        $absPathFile = ABSPATH.$substr1;
        if(file_exists($absPathFile) && is_file($absPathFile)){
            $exists = true;
        }
        else{
            $exists = false;       
        }
        return $exists;
    }

    public function getErrno(){return $this->errno;}
    public function getError(){
        switch($this->errno){
            case 0:
                $this->error = null;
                break;
            case IMAGE_NOTINSERTED:
                $this->error = "Image => Impossibile aggiungere la riga alla tabella";
                break;
            case IMAGE_QUERYERROR:
                $this->error = "Image => Query errata";
                break;
            case IMAGE_NOTDELETED:
                $this->error = "Image => Impossibile cancellare la riga selezionata";
                break;
            case IMAGE_NORESULT:
                $this->error = "Image => La ricerca non ha prodotto alcun risultato";
                break;
            case IMAGE_NOTAFFECTED:
                $this->error = "Image => Impossibile modificare la riga selezionata";
                break;
            case IMAGE_INVALIDSTATUS:
                $this->error = "Image => Il valore di stato dell'immagine può essere solo '0' o '1'";
                break;
            case IMAGE_CHANGESTATUSERROR:
                $this->error = "Image => Errore durante il cambio di stato dell'immagine";
                break;
            case IMAGE_IDNOTEXISTS:
                $this->error = "Image => Nessun ID specificato";
                break;
            case IMAGE_SRCNOTEXISTS:
                $this->error = "Image => Nessun percorso specificato";
                break;
            case IMAGE_INVALIDFORMAT:
                $this->error = "Image => Il file specificato non corrisponde ad un'immagine valida";
                break;
            default:
                $this->error = null;
                break;
        }
        return $this->error;
    }
    public function getId(){return $this->id;}
    public function getSrc(){return $this->src;}
    public function getFonte(){return $this->fonte;}
    public function getQuery(){return $this->query;}
    public function getQueries(){return $this->queries;}
    public function isUsed(){
        if($this->used == '1')return true;
        else return false;
    }

    public function setId($id){$this->id = $id;}
    public function setSrc($src){$this->src = $src;}
    public function setFonte($fonte){$this->fonte = $fonte;}


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

    //cerca nel database l'immagine che contiene l'id specificato
    public function getImageInfoById(){
        $ok = false;
        $this->errno = 0;
        if(isset($this->id)){
            $q = <<<SQL
SELECT * FROM `{$this->table}` WHERE `id` = %d;
SQL;
            $this->query = $this->wpdb->prepare($q,$this->id);
            $image = $this->wpdb->get_row($this->query,ARRAY_A);
            if($image !== null){
                if(sizeof($image) > 0){
                    $this->id = $image["id"];
                    $this->src = $image["src"];
                    $this->used = $image["used"];
                    $this->fonte = $image["fonte"];
                    $ok = true;
                }
                else{
                    $this->errno = IMAGE_NORESULT;
                }
            }//if($image !== null)
            else{
                $this->errno = IMAGE_QUERYERROR;
            }
            $this->queries[] = $this->query;
        }//if(isset($this->id))
        else{
            $this->errno = IMAGE_IDNOTEXISTS;
        }
        return $ok;
    }

    //cerca nel database l'immagine che contiene il sorgente specificato
    public function getImageInfoBySrc(){
        $ok = false;
        $this->errno = 0;
        if(isset($this->src)){
            $q = <<<SQL
SELECT * FROM `{$this->table}` WHERE `src` = '%s';
SQL;
            $this->query = $this->wpdb->prepare($q,$this->src);
            $image = $this->wpdb->get_row($this->query,ARRAY_A);
            if($image !== null){
                if(sizeof($image) > 0){
                    $this->id = $image["id"];
                    $this->src = $image["src"];
                    $this->used = $image["used"];
                    $this->fonte = $image["fonte"];
                    $ok = true;
                }
                else{
                    $this->errno = IMAGE_NORESULT;
                }
            }//if($image !== null)
            else{
                $this->errno = IMAGE_QUERYERROR;
            }
            $this->queries[] = $this->query;
        }//if(isset($this->src))
        else{
            $this->errno = IMAGE_SRCNOTEXISTS;
        }
        return $ok;
    }

    //imposta l'immagine dell'oggetto istanziato
    public function setImage(){
        $html = '';
        $this->errno = 0;
        if(isset($this->src)){
            $src = $this->src;
            $type = wp_check_filetype($src);
                //cotrolla che il file sia un'immagine
                if(in_array($type['type'],Image::$mimes)){
                    $set = $this->setUsedStatus('1');
                    if($set){
                        $html = <<<HTML
<style>
#login h1 a,.login h1 a{
    background-image: url('{$src}') !important;
    background-repeat: no-repeat;
    background-size: 80px 80px;
}
</style>
HTML;
                    }//if($set)
            }//if(in_array($type['type'],Image::$mimes))
            else{
                $this->errno = IMAGE_INVALIDFORMAT;
            }
        }//if(isset($this->src))
        else{
            $this->errno = IMAGE_SRCNOTEXISTS;
        }
        echo $html;
    }

    public function setUsedStatus($status){
        $ok = false;
        $this->errno = 0;
        $set0 = true;
        if($status == '1'){
            //controlla se un'altra immagine della libreria è usata nella pagina di login
            $preUsed = $this->checkUsed();
            if($preUsed !== null){
                //se esiste un'immagine precedente usata nella pagina di login,viene rimossa la funzione che la visualizza
                $set0 = $preUsed->setUsedStatus('0');
            }
        }
        if($set0){
            if($status == '0' || $status == '1'){
                $q = <<<SQL
UPDATE `{$this->table}` SET `used` = '%s' WHERE `id` = '{$this->id}';
SQL;
                    $this->query = $this->wpdb->prepare($q,$status);
                    $update = $this->wpdb->query($this->query);
                    $this->queries[] = $this->query;
                    //$update = $this->wpdb->update($this->table,array('used' => 'status'),array('id' => $this->id),array('%s'),array('%d'));
                    if($update !== false){
                        //la query ha prodotto delle modifiche
                        if($update > 0){
                            $this->used = $status;
                            $ok = true;   
                        }//if($update > 0)
                        else{
                            $this->errno = IMAGE_NOTAFFECTED;
                        }
                    }//if($update !== false)
                    else{
                        $this->errno = IMAGE_QUERYERROR;
                    }
                }//if($status == '0' || $status == '1')
            }//if($set0)
        else{
            $this->errno = IMAGE_INVALIDSTATUS;
        }
        return $ok;
    }
}

?>