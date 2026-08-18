<?php

\defined('_JEXEC');

use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\HTML\Helpers\Bootstrap;


Bootstrap::framework();

$wa = Factory::getApplication()->getDocument()->getWebAssetManager();
// style gda
$wa->useStyle('com_gdadhesions.gda');
$wa->useScript('com_gdadhesions.niveau');

$model = $this->getModel();
// id de la modal pour extraire les niveau du site de la ffessm
$id_modal  = 'FFESSMmodal';

if ($this->profil !== null):
?>

<div class="row">                                

  <?php    // le profil du user 
    echo $model->showCardProfil($this->profil,$id_modal); 
    //les profil OB
    //   foreach ($profilsOB as $profilOB) {
    //     echo $model->showCardProfil($profilOB,false); 
    //   }
  ?>


</div>
<?php


echo $model->showModalffessm($id_modal , $this->profil);




/**
 *   Javascript
 */
?>
<script type="module">

//import JoomlaDialog from 'joomla.dialog';

document.addEventListener('DOMContentLoaded', function () {

var myModal = document.getElementById('<?php echo $id_modal;?>')

myModal.addEventListener('show.bs.modal', function (event) {     
    console.log ("Ouverure de <?php echo $id_modal;?>")
    openFFESSMmodal(myModal)
});

 /**
   *  Requete Ajax
   */
  document.getElementById('ScrapForm').addEventListener('click', function(e) {

    e.preventDefault();
    let formData = new FormData(document.getElementById("extractForm"));   
    Joomla.request({
      method: 'POST',
      url: 'index.php?option=com_gdadhesions&task=niveau.extract&format=json',
      promise: false,
      data: formData,
      onBefore(xhr) {
          xhr.upload.addEventListener('progress', (event) => {
              console.log('Progres', event.loaded, event.total);
          });
      },
      onSuccess: (data) => {
        const response = JSON.parse(data)
        if (response.success) {
            let divResultat = document.getElementById("resultat");  
            response.data.forEach((element, index) => {
                console.log (element)
                let li_brevet = document.createElement("li")
                li_brevet.textContent = element.brevet
                divResultat.append(li_brevet)
            })
        } 
        console.log (response.message);
        
      },
      onError(xhr) {
        const response = JSON.parse(xhr.response);
      }
    })

  })


})
</script>

 <?php
	else:
		echo "UserName inconnue";
	endif;