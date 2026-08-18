
const openFFESSMmodal = function (Element) {

    input = Element.querySelector('input#url')
    scrapButton = Element.querySelector('#scrap_button')

    input.addEventListener('change', function (event) {
        scrapButton.removeAttribute('disabled')
    })

    // scrapButton.addEventListener('click', function (event) {
    //     url = input.value
    //     extractInfoFromPage(url).then(info => {
    //         console.log(info);
    //     });


    // })


    // async function extractInfoFromPage(url) {
    //     const proxyUrl = 'https://cors-anywhere.herokuapp.com/';
    //     let maRequete = new Request(proxyUrl + url);
    //     let mesEntetes = new Headers();
    //     mesEntetes.append('Content-Type', 'text/html'); 
    //     mesEntetes.append('Accept', 'text/html');    

    //     const monInit = { method: 'GET', headers: mesEntetes,};
    //     fetch(maRequete,monInit)
    //         .then(function (reponse) {
    //             console.log( reponse) 
    //             if (!reponse.ok) {
    //             throw new Error(`erreur HTTP! statut: ${reponse.status}`);
    //             }
    //             return reponse.blob();
    //         })
    //         .then(function (reponse) {
    //             // let URLobjet = URL.createObjectURL(reponse);
    //             // console.log( URLobjet)

    //             const html = reponse.text();
    //             const parser = new DOMParser();
    //             const doc = parser.parseFromString(html, 'text/html');

    //             // Extract the desired information
    //             doc.querySelectorAll('.card').forEach(function (el) {
    //                 console.log (el.textContent.trim())
    //             })
    //             console.log( doc)

    //         });


        // try {
        //     // Fetch the HTML content of the page
        //     const response =  fetch(url);
        //     if (!response.ok) {
        //         throw new Error('Network response was not ok');
        //     }
        //     const html = await response.text();
    
        //     // Parse the HTML content
        //     const parser = new DOMParser();
        //     const doc = parser.parseFromString(html, 'text/html');
    
        //     // Extract the desired information
        //     doc.querySelectorAll('.card').forEach(function (el) {
        //         console.log (el.textContent.trim())
        //     })
    
        //     // Return the information as an array
        //     // return [
        //     //     { label: 'niveau', value: niveau },
        //     //     { label: 'lieu', value: lieu }
        //     // ];
        // } catch (error) {
        //     console.error('Error extracting information:', error);
        //     return [];
        // }
    // }

}