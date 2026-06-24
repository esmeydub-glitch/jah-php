(function () {
  function findTargetVideo(button) {
    var target = button.getAttribute('data-video-target');
    if (target) {
      return document.getElementById(target);
    }

    var root = button.closest('.asjah-scene') || document;
    return root.querySelector('video');
  }

  function emitSignedEvent(button) {
    var eventName = button.getAttribute('data-jah-event') || '';
    var componentId = button.getAttribute('data-jah-component') || button.id || '';
    var token = button.getAttribute('data-salk-token') || '';

    button.dispatchEvent(new CustomEvent('asjah:signed-event', {
      bubbles: true,
      detail: {
        event: eventName,
        component_id: componentId,
        salk_token: token
      }
    }));
  }

  document.addEventListener('click', function (event) {
    var button = event.target.closest('[data-jah-event]');
    if (!button) {
      return;
    }

    var eventName = button.getAttribute('data-jah-event');
    var video = findTargetVideo(button);

    if (eventName === 'jah.video.play' && video) {
      video.play();
      emitSignedEvent(button);
      return;
    }

    if (eventName === 'jah.video.pause' && video) {
      video.pause();
      emitSignedEvent(button);
      return;
    }

    emitSignedEvent(button);
  });
}());
