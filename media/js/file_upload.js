//import {JoomlaDialog} from 'joomla.dialog';
// const JoomlaDialog = require('joomla.dialog');
/** Class manage download */



// function file_upload  (mediaBrowser, dropArea,previewImage,imputImage,dialog)
// {
  class FileUpload {

  /** Define event  */
  constructor( mediaBrowser, dropArea,previewImage,imputImage, flag ) {   

      this._accepted = ["image/jpeg", "image/png", "image/jpg","image/webp"];
      this._dropArea = dropArea;
      this._imputImage = imputImage;
      this._previewImage = previewImage;
         // Flag qui permet de signaler si un fichier a été uploader
      this._flag = flag;
      // this._dialog = dialog;

      let _file = null;
      let _tmpfile = null;

      ["dragenter", "dragover", "dragleave", "drop"].forEach((eventName) => {
            mediaBrowser.addEventListener(eventName, this.preventDefaults, false);
            document.body.addEventListener(eventName, this.preventDefaults, false);
          
          });

          ["dragenter", "dragover"].forEach((eventName) => {
            mediaBrowser.addEventListener(eventName, function (e) {
              dropArea.classList.add("active");
            });
          });

          ["dragleave", "drop"].forEach((eventName) => {
              mediaBrowser.addEventListener(eventName, function (e) {
                dropArea.classList.remove("active");
              });
          });

          mediaBrowser.addEventListener("drop", this.handleDrop.bind(this), false);

          // Declenche la selection du fichier à uploader
          imputImage.addEventListener("change", this.handleFiles.bind(this), false);

          // Event sur le click de l'image preview pour selectionner une nouvelle image a uploader
          previewImage.addEventListener('click', function () {
            imputImage.click();
          });

          /** Creation de la boite de dialog */
      //   this._dialog = new JoomlaDialog({
      //     popupType: 'inline',  
      //     className: 'joomla-dialog-alert'               
      //   });
      //   dialog.popupButtons = [
      //     { label: 'Ok', onClick: () => dialog.destroy(), className: 'btn btn-warning ms-2' }]
      // dialog.show();


  }
  //** delete the prevent event */ 
  preventDefaults(e) {
      e.preventDefault();
      e.stopPropagation();
    }

  get File() {
      return this._file;
  }

    // lache le fichier  from Drop
     handleDrop(e) {
      let dt = e.dataTransfer;
      // this.handleFiles(dt.files);
      [...dt.files].forEach(this.uploadFile.bind(this));
    }

    /** Gerer les fichier 
     * Il doit y avoir 1 seule fichier 
     * 
    */
     handleFiles(e) {
      // console.debug ( this._imputImage.files[0]);
      this.uploadFile(this._imputImage.files[0]);
    }


    /** Telecharge le fichier et  le previsualise*/
    uploadFile(file) {    
        const fileReader = new FileReader();
        this._tmpfile = file;
        fileReader.addEventListener("loadend", this.fileOnLoaded.bind(this), false);
        fileReader.readAsDataURL(this._tmpfile);
      };
      
      /** 
       * Une fois le fichier chargé, lancez le traitement.  
       */
      fileOnLoaded (e)
      {
      // fileReader.onloadend = function (e) {
        if (this.isValidFileType(this._tmpfile)) {
          this._previewImage.src = e.target.result
          this._file = this._tmpfile;
          this._flag.value = '1';
        }
        // Si le fichier est invalide, isValidFileType() a déjà affiché l'erreur via Joomla.renderMessages
    }




   /** 
    * We’ll discuss `isValidFileType` function down the road
    */

    isValidFileType(file) {

      //console.debug (file)
      if (file.size > 3097152) { 
        Joomla.renderMessages({error: ['Le fichier "' + file.name + '" est trop gros : ' + Math.round(file.size/1048576 * 100)/100 + ' Mo. Votre fichier ne doit pas depasser 2 Mo.']});
        // this._dialog.popupContent = 'Le fichier "' + file.name + '" est trop gros : ' + Math.round(file.size/1048576 * 100)/100 + ' Mo.';
        // this._dialog.popupContent += '<br>Votre fichier ne doit pas depasser 2 Mo.'
        return false;}
      if ( this._accepted.includes(file.type) === false) {
        Joomla.renderMessages({error: ['Le format "' + file.type + '" n\'est pas accepté. Uniquement les images de type jpeg et png sont acceptées']});
        // this._dialog.popupContent = 'Le format <b>"' + file.type + '"</b> n\'est pas accepté.';
        // this._dialog.popupContent += '<br>Uniquement les images de type <b>jpeg</b> et <b>png</b> sont acceptées'
        return false
      }
      return true;
    }

    /**
     * Factory générique : câble une zone de drag&drop à partir d'IDs DOM et enregistre
     * l'instance dans window.GdaFileUploads[name] pour un accès partagé entre vues.
     *
     * @param {string} name Clé du registre (ex: 'photo', 'caci')
     * @param {Object} ids
     * @param {string} ids.mediaBrowserId Conteneur qui capte le drag&drop
     * @param {string} ids.dropAreaId Zone visuelle mise en surbrillance pendant le drag
     * @param {string} ids.previewId Balise <img> de prévisualisation
     * @param {string} ids.inputId Input file caché
     * @param {string} ids.flagId Input hidden signalant qu'un fichier a été sélectionné
     * @param {string} [ids.captureInputId] Input file optionnel dédié à la prise de photo (doit porter
     *   statiquement les attributs accept="image/*" et capture="environment" dans le HTML : ces attributs
     *   ne sont pas fiables lorsqu'ils sont ajoutés dynamiquement en JS sur certains navigateurs mobiles).
     * @returns {FileUpload|null}
     */
    static create(name, { mediaBrowserId, dropAreaId, previewId, inputId, flagId, captureInputId } = {}) {
      const mediaBrowser = document.getElementById(mediaBrowserId);
      const dropArea = document.getElementById(dropAreaId);
      const previewImage = document.getElementById(previewId);
      const imputImage = document.getElementById(inputId);
      const flag = document.getElementById(flagId);

      if (!mediaBrowser || !dropArea || !previewImage || !imputImage || !flag) {
        console.debug('FileUpload.create("' + name + '") : un ou plusieurs éléments DOM sont introuvables.');
        return null;
      }

      const instance = new FileUpload(mediaBrowser, dropArea, previewImage, imputImage, flag);

      if (captureInputId) {
        const captureInput = document.getElementById(captureInputId);
        if (captureInput) {
          captureInput.addEventListener('change', () => {
            if (captureInput.files && captureInput.files[0]) {
              instance.uploadFile(captureInput.files[0]);
              // Réinitialise l'input pour permettre de reprendre une nouvelle photo ensuite
              captureInput.value = '';
            }
          });
        }
      }

      window.GdaFileUploads = window.GdaFileUploads || {};
      window.GdaFileUploads[name] = instance;

      return instance;
    }

}