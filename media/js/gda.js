document.addEventListener('DOMContentLoaded', () => {

  const container = document.getElementById('system-message-container');

  if (!container) {
    return;
  }

  const observer = new MutationObserver((mutations) => {
    mutations.forEach(mutation => {
      mutation.addedNodes.forEach(node => {
        if (node.tagName === 'JOOMLA-ALERT') {
          setTimeout(() => {
            node.style.transition = 'opacity .4s';
            node.style.opacity = '0';
            setTimeout(() => node.remove(), 400);
          }, 10000);
        }
      });
    });

  });
  observer.observe(container, {
    childList: true
  });

});