<?php
require_once("../../../wp-load.php");
require_once('classi/Library.php');

$imgPath = get_home_url().'/wp-content/plugins/loginLogoMin/img/';
$aPath = ABSPATH.'wp-content/plugins/loginLogoMin/img/';
$logFile = ABSPATH.'logLibrary.txt';
$mimes = array('image/gif','image/jpeg','image/png');
$risposta = array();
$risposta['msg'] = '';
$risposta['done'] = '0';
if(isset($_FILES['file-0']))$risposta['file'] = $_FILES['file-0'];

//immagine scelta dalla libreria
if(isset($_POST['action']) && $_POST['action'] != ''){
    $action = $_POST['action'];
    //cerca le immagini della libreria
    if($action == 'read'){
        $risposta['libreria'] = ll_library_content();
        $risposta['action'] = 'read';
        if(!empty($risposta['libreria'])){
            $risposta['done'] = '1';
        }
        else{
            $risposta['empty'] = '1';
            $risposta['emptyMsg'] = 'Nessuna immagine nella libreria';
        }
    }//if($action == 'read')
    //percorso assoluto dell'immagine
    if(isset($_POST['src']) && $_POST['src'] != ''){
        $j = 0;
        $src = $_POST['src'];
        try{
            $library = new Library(); 
            //aggiunge un immagine alla libreria 
            if($action == 'add'){
                //E' possibile caricare solo immagini
                $type = wp_check_filetype($src);
                if(in_array($type['type'],$mimes)){
                    $dati = array('src' => $src, 'fonte' => 1);
                    try{
                        $image = new Image($dati);
                        $add = $library->Add($image);
                        $errno = $library->getErrno();
                        switch($errno){
                            case 0:
                                $risposta['done'] = '1';
                                //$risposta['libreria'] = ll_library_content();
                                $risposta['msg'] = 'Immagine aggiunta alla libreria';
                                $risposta['libreria'] = ll_library_content();
                                $risposta['action'] = 'add';
                                break;
                            case LIBRARY_IMAGEALREADYEXISTS:
                                $risposta['msg'] = "L'immagine che si vuole aggiungere esiste già";
                                break;
                            case LIBRARY_COPYFAILED:
                            case LIBRARY_INVALIDPATHS:
                            case LIBRARY_QUERYERROR:
                            default:
                                $risposta['msg'] = "Errore sconosciuto: Codice {$errno}";
                                break;
                        }//switch($errno){                  
                    }
                    catch(Exception $e){
                        $risposta['msg'] = $e->getMessage();
                    }
                }//if(in_array($type['type'],$mimes))
                else{
                    $risposta['msg'] = "Il file che stai cercando di caricare non corrisponde ad un'immagine";
                }
            }//if($action == 'add')
        }
        catch(Exception $e){
            $risposta['msg'] = $e->getMessage();
        }
    }//if(isset($_POST['src']) && $_POST['src'] != '')
    if(isset($_POST['id']) && is_numeric($_POST['id'])){
        $idImg = $_POST['id'];
        try{
            $library = new Library();
            $image = $library->getImageById($idImg);
            if($image != null){
                if($action == 'change'){
                    $set = $library->setUsedImage($image);
                    $args = array('src' => $image->getSrc());
                    //operazione andata a buon fine
                    if($set){
                        $risposta['action'] = 'change';
                        $risposta['done'] = '1';
                        $risposta['msg'] = "L' immagine della pagina di login è stata modificata";
                    }
                    else{
                        $risposta['msg'] = $library->getError();
                        $risposta['msg'] .= ' => add_action fuori dalla classe';
                    }
                    $risposta['libreria'] = ll_library_content();
                    if(empty($risposta['libreria'])){
                        $risposta['empty'] = '1';
                        $risposta['emptyMsg'] = 'Nessuna immagine nella libreria';
                    }
                }//if($action == 'change')
                //L'utente vuole eliminare un'immagine dalla libreria
                else if($action == 'delete'){
                    $del = $library->Delete($image);
                    //operazione andata a buon fine
                    if($del){
                        $risposta['action'] = 'delete';
                        $risposta['done'] = '1';
                        $risposta['msg'] = 'Immagine rimossa dalla libreria';
                        $risposta['libreria'] = ll_library_content();
                        if(empty($risposta['libreria'])){
                            $risposta['empty'] = '1';
                            $risposta['emptyMsg'] = 'Nessuna immagine nella libreria';
                        }
                    }
                    else $risposta['msg'] = $library->getError();
                }//else if($action == 'delete')
                //deseleziona l'immagine di sfondo della pagina di login
                else if($action == 'deselect'){
                    $image->setUsedStatus('0');
                    if($image->getErrno() == 0){
                        $risposta['action'] = 'deselect';
                        $risposta['done'] = '1';
                        $risposta['msg'] = "L'immagine selezionata non comparirà più nella pagina di login";
                        $risposta['libreria'] = ll_library_content();
                    }//if($image->getErrno() == 0)
                    else{
                        $risposta['msg'] = $image->getError();
                    } 
                }//else if($action == 'deselect')
            }
            else $risposta['msg'] = $library->getError();    
            //L'utente vuole cambiare l'immagine di logo della pagina di login
        }
        catch(Exception $e){
            $risposta['msg'] = $e->getMessage();
        }      
    }//if(isset($_POST['id']) && is_numeric($_POST['id'])) 
    //Pulsante "CARICA FILE"
    if(isset($_POST['buttonUpload'])){
        if($action == 'add'){
            $type = $_FILES['file-0']['type'];
            //controlla se è un file di tipo immagine
            if(in_array($type,$mimes)){
                $source = $_FILES['file-0']['tmp_name'];
                $name = str_replace(" ","_",$_FILES['file-0']['name']);
                $dest = './img/';
                try{
                    $library = new Library();
                    $imgData = array(
                        'src' => $imgPath.$name,
                        'fonte' => 0
                    );
                    $image = new Image($imgData);
                    $add = $library->Add($image,$source,$aPath.$name);
                    if($add){
                        $risposta['action'] = 'add';
                        $risposta['done'] = '1';
                        $risposta['msg'] = 'Immagine aggiunta alla libreria';
                        $risposta['libreria'] = ll_library_content();
                        $risposta['queries'] = $library->getQueries();
                    }
                    else{
                        $risposta['msg'] = $library->getError();
                        $risposta['queries'] = $library->getQueries();
                    }
                }
                catch(Exception $e){
                    $risposta['msg'] = $e->getMessage();
                }
            }//if(in_array($type['type'],$mimes)){
            else{
                $risposta['msg'] = "Il file che stai cercando di caricare non corrisponde ad un'immagine"; 
            }
        }
    } 
}//if(isset($_POST['action']) && $_POST['action'] != '')
else{
    $risposta['msg'] = 'La variabile "action" non esiste o non ha un valore valido';
}

//informazioni sulle immagini della libreria
function ll_library_content(){
    global $risposta;
    global $logFile;
    $imgInfo = array();
    $library = new Library();
    //Array di oggetti Image contenuti nella libreria
    $immagini = $library->getImages();
    if($library->getErrno() == 0){
        $i = 0;
        foreach($immagini as $img){
            $imgInfo[$i]['id'] = $img->getId();
            $imgInfo[$i]['src'] = $img->getSrc();
            $imgInfo[$i]['used'] = $img->isUsed();
            $imgInfo[$i]['fonte'] = $img->getFonte();
            $i++;           
        }
    }
    else{
        $risposta['queries'][] = $library->getQueries(); 
        $risposta['msg'] = "Dopo getImages(): ".$library->getError()."\r\n"; 
    }
    return $imgInfo;
}

echo json_encode($risposta);

?>