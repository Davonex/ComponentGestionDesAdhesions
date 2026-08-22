//import {JoomlaDialog} from 'joomla.dialog';
// const JoomlaDialog = require('joomla.dialog');
/** Class manage download */

// Capturé à l'exécution (synchrone) du script : sert de base pour charger pdf.js
// depuis le même dossier, quelle que soit la vue qui inclut file_upload.js.
const GDA_FILE_UPLOAD_BASE_URL = document.currentScript ? document.currentScript.src : '';
let gdaPdfJsPromise = null;

/** Charge pdf.js (module ES) une seule fois, uniquement quand un PDF est réellement sélectionné. */
function loadPdfJs() {
  if (!gdaPdfJsPromise) {
    gdaPdfJsPromise = import(new URL('pdf.min.js', GDA_FILE_UPLOAD_BASE_URL).href).then((pdfjsLib) => {
      pdfjsLib.GlobalWorkerOptions.workerSrc = new URL('pdf.worker.min.js', GDA_FILE_UPLOAD_BASE_URL).href;
      return pdfjsLib;
    });
  }
  return gdaPdfJsPromise;
}

// function file_upload  (mediaBrowser, dropArea,previewImage,imputImage,dialog)
// {
  class FileUpload {

  /** Define event  */
  constructor( mediaBrowser, dropArea,previewImage,imputImage, flag, acceptPdf = false, onError = null ) {

      this._accepted = ["image/jpeg", "image/png", "image/jpg","image/webp"];
      this._maxSize = 3097152; // ~2.95 Mo
      this._acceptPdf = acceptPdf;
      this._dropArea = dropArea;
      this._imputImage = imputImage;
      this._previewImage = previewImage;
         // Flag qui permet de signaler si un fichier a été uploader
      this._flag = flag;
      // Popup d'erreur optionnelle (ex: showAdhesionAlertText côté vue Adhésion) ; à défaut,
      // repli sur le bandeau de messages Joomla - utilisé notamment par la popup d'édition de
      // profil de la vue Utilisateurs (form_modal.js), qui n'a pas ce mécanisme.
      this._onError = onError;
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

  /** Affiche un message d'erreur via le callback onError si fourni, sinon le bandeau Joomla. */
  _showError(message) {
    if (typeof this._onError === 'function') {
      this._onError(message);
    } else {
      Joomla.renderMessages({ error: [message] });
    }
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
        if (this._acceptPdf && file.type === 'application/pdf') {
          this.convertPdfToImage(file);
          return;
        }
        const fileReader = new FileReader();
        this._tmpfile = file;
        fileReader.addEventListener("loadend", this.fileOnLoaded.bind(this), false);
        fileReader.readAsDataURL(this._tmpfile);
      };

      /**
       * Convertit la 1ère page d'un PDF en image JPEG, puis poursuit le flux normal
       * (preview, flag, _file) via uploadFile() comme si un JPEG avait été sélectionné.
       * Le serveur ne reçoit donc jamais de PDF.
       */
      async convertPdfToImage(file) {
        if (this.isFileTooLarge(file)) {
          return;
        }
        try {
          const pdfjsLib = await loadPdfJs();
          const pdf = await pdfjsLib.getDocument({ data: await file.arrayBuffer() }).promise;
          const page = await pdf.getPage(1);
          const viewport = page.getViewport({ scale: 2 });
          const canvas = document.createElement('canvas');
          canvas.width = viewport.width;
          canvas.height = viewport.height;
          await page.render({ canvasContext: canvas.getContext('2d'), viewport }).promise;

          const blob = await new Promise((resolve) => canvas.toBlob(resolve, 'image/jpeg', 0.85));
          if (!blob) {
            throw new Error('canvas.toBlob a renvoyé un résultat vide');
          }

          const imageFile = new File([blob], file.name.replace(/\.pdf$/i, '.jpg'), { type: 'image/jpeg' });
          this.uploadFile(imageFile);
        } catch (e) {
          console.error(e);
          this._showError('Le fichier PDF "' + file.name + '" n\'a pas pu être converti en image.');
        }
      }


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

    /** Signale (et affiche l'erreur) si le fichier dépasse la taille max autorisée */
    isFileTooLarge(file) {
      if (file.size > this._maxSize) {
        this._showError('Le fichier "' + file.name + '" est trop gros : ' + Math.round(file.size/1048576 * 100)/100 + ' Mo. Votre fichier ne doit pas depasser 2 Mo.');
        return true;
      }
      return false;
    }

    isValidFileType(file) {

      //console.debug (file)
      if (this.isFileTooLarge(file)) {
        return false;
      }
      if ( this._accepted.includes(file.type) === false) {
        this._showError('Le format "' + file.type + '" n\'est pas accepté. Uniquement les images de type jpeg et png sont acceptées');
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
     * @param {boolean} [ids.acceptPdf] Si true, un PDF déposé/sélectionné est converti en JPEG
     *   (1ère page) avant d'être traité comme une image. pdf.js n'est chargé que dans ce cas.
     * @param {Function} [ids.onError] Callback(message) pour les erreurs de validation de fichier.
     *   Repli sur Joomla.renderMessages() si absent.
     * @returns {FileUpload|null}
     */
    static create(name, { mediaBrowserId, dropAreaId, previewId, inputId, flagId, captureInputId, acceptPdf, onError } = {}) {
      const mediaBrowser = document.getElementById(mediaBrowserId);
      const dropArea = document.getElementById(dropAreaId);
      const previewImage = document.getElementById(previewId);
      const imputImage = document.getElementById(inputId);
      const flag = document.getElementById(flagId);

      if (!mediaBrowser || !dropArea || !previewImage || !imputImage || !flag) {
        console.debug('FileUpload.create("' + name + '") : un ou plusieurs éléments DOM sont introuvables.');
        return null;
      }

      const instance = new FileUpload(mediaBrowser, dropArea, previewImage, imputImage, flag, !!acceptPdf, onError || null);

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