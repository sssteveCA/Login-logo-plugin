<?php
header('Content-type: text/javascript');
require_once("../../../../wp-load.php");
require_once('../const.php');

$urlAjax = get_home_url()."/wp-content/plugins/".DIR_PLUGIN."/libraryAction.php";

$js = <<<JS
var htmlDivTable = '';
var divTable;

jQuery(document).ready( function($) {

    //Lettura dei dati dal database
    jQuery.ajax({
        url : "$urlAjax",
        method : 'post',
        data : {
            action : 'read'
        },
        success : function(risposta, stato, xhr){
            try{ 
                //console.log(risposta);
                var risp = JSON.parse(risposta);
                console.log(risp);
                if(risp.done == '1'){
                    htmlDivTable = tabella(risp.libreria);
                    jQuery('#ll_div_tabella').html(htmlDivTable);
                    eventiTabella();
                }
                if(risp.hasOwnProperty('empty')){
                    if(risp.empty == '1'){
                        jQuery('#ll_div_tabella').html(risp.emptyMsg);
                    }
                }
            }
            catch(e){
                console.warn(e);
            }
        },
        complete : function(xhr, stato){

        },
        error : function(xhr, stato, errore){
            console.log(xhr);
        }
    });//jQuery.ajax({

    jQuery('#ll_button_upload').click(function(){
        var formData = new FormData();
        jQuery.each(jQuery('#ll_file_upload')[0].files,function(i, file){
            formData.append('file-'+i, file);
        });
        //pulsante carica file
        formData.append('buttonUpload','1');
        formData.append('action','add');
        jQuery.ajax({
            url : "$urlAjax",
            data : formData,
            cache : false,
            contentType : false,
            processData : false,
            method : 'post',
            success : function(risposta, stato, xhr){
                //console.log(risposta);
                try{
                    var risp = JSON.parse(risposta);
                    console.log(risp);
                    if(risp.hasOwnProperty('libreria') && !risp.hasOwnProperty('empty')){
                            htmlDivTable = tabella(risp.libreria);
                            jQuery('#ll_div_tabella').html(htmlDivTable);
                            eventiTabella();
                    }
                    if(risp.hasOwnProperty('empty')){
                        if(risp.empty == '1'){
                            jQuery('#ll_div_tabella').html(risp.emptyMsg);
                        }
                    }
                }
                catch(e){
                    console.warn(e);
                }
            },
            complete : function(xhr, stato){
            },
            error : function(xhr, stato, errore){
                console.log(xhr);
            }
        });//jQuery.ajax({
    });

    jQuery('input#myprefix_media_manager').click(function(e) {

           e.preventDefault();
           var image_frame;
           if(image_frame){
               image_frame.open();
           }
           // Define image_frame as wp.media object
           image_frame = wp.media({
                         title: 'Select Media',
                         multiple : false,
                         library : {
                              type : 'image',
                          }
                     });

                     image_frame.on('close',function() {
                        // On close, get selections and save to the hidden input
                        // plus other AJAX stuff to refresh the image preview
                        var selection =  image_frame.state().get('selection');
                        var gallery_ids = new Array();
                        var my_index = 0;
                        selection.each(function(attachment) {
                           gallery_ids[my_index] = attachment['id'];
                           my_index++;
                        });
                        var ids = gallery_ids.join(",");
                        jQuery('input#myprefix_image_id').val(ids);
                        Refresh_Image(ids);
                     });

                    image_frame.on('open',function() {
                      // On open, get the id from the hidden input
                      // and select the appropiate images in the media manager
                      var selection =  image_frame.state().get('selection');
                      var ids = jQuery('input#myprefix_image_id').val().split(',');
                      ids.forEach(function(id) {
                        var attachment = wp.media.attachment(id);
                        attachment.fetch();
                        selection.add( attachment ? [ attachment ] : [] );
                      });

                    });

                  image_frame.open();
   });
});

// Ajax request to refresh the image preview
function Refresh_Image(the_id){
      var data = {
          action: 'myprefix_get_image',
          id: the_id
      };

jQuery.get(ajaxurl, data, function(response) {
    if(response.success === true) {
            jQuery('#myprefix-preview-image').replaceWith( response.data.image );
            //console.log(response.data.image);
            var image = response.data.image;
            image = jQuery.parseHTML(image);
            //console.log(image);
            src = jQuery(image[0]).attr('src');
            //console.log(src);
            jQuery.ajax({
                url : "$urlAjax",
                method : 'post',
                data : {
                    src : src,
                    action : 'add'
                },
                success : function(risposta, stato, xhr){
                    //console.log(risposta);
                    try{ 
                        var risp = JSON.parse(risposta);
                        console.log(risp);
                        if(risp.hasOwnProperty('libreria') && !risp.hasOwnProperty('empty')){
                            htmlDivTable = tabella(risp.libreria);
                            jQuery('#ll_div_tabella').html(htmlDivTable);
                            eventiTabella();
                        }
                        if(risp.hasOwnProperty('empty')){
                            if(risp.empty == '1'){
                                jQuery('#ll_div_tabella').html(risp.emptyMsg);
                            }
                        }
                    }
                    catch(e){
                        console.warn(e);
                    }
                },
                complete : function(xhr, stato){

                },
                error : function(xhr, stato, errore){
                    console.log(xhr);
                }
            });//jQuery.ajax
        }//if(response.success === true)
    });
}

function tabella(datiTabella){
    var htmlTable = '';
    var usata = '';
    var src = '';
    var fonte = '';
    htmlTable += '<table id="tLibreria" border="1">';
    htmlTable += '<th></th><th>Miniatura</th><th>Usata nella pagina di login</th><th>Imposta immagine di login</th><th>Elimina dalla libreria</th><th>Fonte</th>';
    for(var i in datiTabella){
        var j = parseInt(i)+1;
        usata = datiTabella[i]['used'];
        src = datiTabella[i]['src'];
        fonte = datiTabella[i]['fonte'];
        htmlTable += '<tr>';
        htmlTable += '<td>'+j+'<input type="hidden" class="ll_form_id" value="'+datiTabella[i]['id']+'"></td>';
        htmlTable += '<td><img src="'+src+'" alt="immagine libreria" width="150" height="150"></td>';
        if(usata == true){
            htmlTable += '<td>Sì</td>';
            htmlTable += '<td><input type="button" id="ll_form_deseleziona" value="DESELEZIONA"></td>';         
        }
        else{
            htmlTable += '<td>No</td>';
            htmlTable += '<td><input type="button" class="ll_form_cambia" value="CAMBIA"></td>';
        }
        htmlTable += '<td><input type="button" class="ll_form_elimina" value="ELIMINA"></td>';
        if(fonte == true){
            htmlTable += '<td>Libreria</td>';
        }
        else{
            htmlTable += '<td>Caricata dall\'utente</td>';
        }
        htmlTable += '</tr>';
    }
    htmlTable += '</table>';
    return htmlTable; 
}

function eventiTabella(){

    var td,tr,idImm;
    //Pulsante per cambiare l'immagine della pagina di login
    jQuery('.ll_form_cambia').click(function(){
        tr = jQuery(this).parent().parent();
        td = jQuery(tr).children().eq(0);
        //input type hidden che contiene l'id dell'immagine
        idImm = jQuery(td).children().eq(0).val();
        jQuery.ajax({
            url : "$urlAjax",
            method : 'post',
            data : {
                action : 'change',
                id : idImm 
            },
            success : function(risposta, stato, xhr){
                //console.log(risposta);
                try{
                    var risp = JSON.parse(risposta);
                    console.log(risp);
                    if(risp.hasOwnProperty('libreria') && !risp.hasOwnProperty('empty')){
                        htmlDivTable = tabella(risp.libreria);
                        jQuery('#ll_div_tabella').html(htmlDivTable);
                        eventiTabella();
                    }
                    if(risp.hasOwnProperty('empty')){
                        if(risp.empty == '1'){
                            jQuery('#ll_div_tabella').html(risp.emptyMsg);
                        }
                    }
                }
                catch(e){
                    console.warn(e);
                }
            },
            complete : function(xhr, stato){

            },
            error : function(xhr, stato, errore){
                console.log(xhr);
            }
        });
    });//jQuery('.ll_form_cambia').click(function(){
    //Pulsante per eliminare l'immagine selezionata dalla libreria
    jQuery('.ll_form_elimina').click(function(){
        if(confirm("Vuoi rimuovere l'immagine selezionata?") == true){
            tr = jQuery(this).parent().parent();
            td = jQuery(tr).children().eq(0);
            //input type hidden che contiene l'id dell'immagine
            idImm = jQuery(td).children().eq(0).val();
            jQuery.ajax({
                url : "$urlAjax",
                method : 'post',
                data : {
                    action : 'delete',
                    id : idImm 
                },
                success : function(risposta, stato, xhr){
                    //console.log(risposta);
                    try{
                        var risp = JSON.parse(risposta);
                        console.log(risp);
                        if(risp.hasOwnProperty('libreria') && !risp.hasOwnProperty('empty')){
                            htmlDivTable = tabella(risp.libreria);
                            jQuery('#ll_div_tabella').html(htmlDivTable);
                            eventiTabella();
                        }
                        if(risp.hasOwnProperty('empty')){
                            if(risp.empty == '1'){
                                jQuery('#ll_div_tabella').html(risp.emptyMsg);
                            }
                        }
                    }
                    catch(e){
                        console.warn(e);
                    }
                },
                complete : function(xhr, stato){

                },
                error : function(xhr, stato, errore){
                    console.log(xhr);
                }
            });
        }//if(confirm("Vuoi rimuovere l'immagine selezionata?") == true){
    });//jQuery('.ll_form_elimina').click(function(){
    if(jQuery('#ll_form_deseleziona').length){
        jQuery('#ll_form_deseleziona').click(function(){
            tr = jQuery(this).parent().parent();
            td = jQuery(tr).children().eq(0);
            //input type hidden che contiene l'id dell'immagine
            idImm = jQuery(td).children().eq(0).val();
            jQuery.ajax({
                url : "$urlAjax",
                method : 'post',
                data : {
                    action : 'deselect',
                    id : idImm 
                },
                success : function(risposta, stato, xhr){
                    //console.log(risposta);
                    try{
                        var risp = JSON.parse(risposta);
                        console.log(risp);
                        if(risp.hasOwnProperty('libreria') && !risp.hasOwnProperty('empty')){
                            htmlDivTable = tabella(risp.libreria);
                            jQuery('#ll_div_tabella').html(htmlDivTable);
                            eventiTabella();
                        }
                        if(risp.hasOwnProperty('empty')){
                            if(risp.empty == '1'){
                                jQuery('#ll_div_tabella').html(risp.emptyMsg);
                            }
                        }  
                    }
                    catch(e){
                        console.warn(e);
                    }       
                },
                complete : function(xhr, stato){
                    
                },
                error : function(xhr, stato, errore){
                    console.log(xhr);
                }
            });//jQuery.ajax
        });//jQuery('#ll_form_deseleziona').click(function()
    }
}
JS;
echo $js;

?>