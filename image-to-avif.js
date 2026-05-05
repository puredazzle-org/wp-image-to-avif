document.addEventListener('click', function (e) {
  if (!e.target.classList.contains('image-to-avif-convert')) return;

  var button = e.target;
  var id = button.dataset.id;

  button.disabled = true;
  button.textContent = 'Converting...';

  fetch(window.imageToAvif.ajaxurl, {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: 'action=image_to_avif_convert&nonce=' + window.imageToAvif.nonce + '&attachment_id=' + id,
  })
    .then((r) => r.json())
    .then((data) => {
      button.textContent = data.success ? 'Done!' : 'Error';
      if (data.success) location.reload();
    })
    .catch(() => {
      button.textContent = 'Error';
      button.disabled = false;
    });
});
