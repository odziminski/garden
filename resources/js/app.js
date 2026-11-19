import 'bootstrap';

const randomNameButton = document.querySelector('[data-plant-name]');
if (randomNameButton) {
    const names = ['Basil', 'Clover', 'Fern', 'Ivy', 'Juniper', 'Maple', 'Moss', 'Olive', 'Poppy', 'Sage'];
    const nameInput = document.querySelector('#plant-name');

    randomNameButton.addEventListener('click', () => {
        const options = names.filter((name) => name !== nameInput.value);
        nameInput.value = options[Math.floor(Math.random() * options.length)];
    });
}

const photo = document.querySelector('[data-plant-photo]');
if (photo && navigator.mediaDevices?.getUserMedia) {
    const file = photo.querySelector('[name="avatar"]');
    const webcam = photo.querySelector('[name="webcamAvatar"]');
    const start = photo.querySelector('[data-camera-start]');
    const video = photo.querySelector('[data-camera-video]');
    const snapshot = photo.querySelector('[data-camera-snapshot]');
    const close = photo.querySelector('[data-camera-close]');
    const preview = photo.querySelector('[data-camera-preview]');
    const remove = photo.querySelector('[data-camera-remove]');
    const error = photo.querySelector('[data-camera-error]');
    let pageActive = true;

    const stop = () => {
        video.srcObject?.getTracks().forEach((track) => track.stop());
        video.srcObject = null;
        video.hidden = true;
        snapshot.hidden = true;
        close.hidden = true;
        start.hidden = false;
    };

    const clearPhoto = () => {
        webcam.value = '';
        preview.removeAttribute('src');
        preview.hidden = true;
        remove.hidden = true;
    };

    photo.querySelector('[data-camera-controls]').hidden = false;
    video.hidden = true;

    start.addEventListener('click', async () => {
        error.textContent = '';
        start.disabled = true;
        try {
            const stream = await navigator.mediaDevices.getUserMedia({
                video: { facingMode: { ideal: 'environment' } },
                audio: false
            });

            if (!pageActive) {
                stream.getTracks().forEach((track) => track.stop());
                return;
            }

            video.srcObject = stream;
            video.hidden = false;
            snapshot.hidden = false;
            close.hidden = false;
            start.hidden = true;
        } catch {
            error.textContent = 'Could not open the camera. You can choose an image file instead.';
        } finally {
            start.disabled = false;
        }
    });

    snapshot.addEventListener('click', () => {
        if (!video.videoWidth || !video.videoHeight) {
            return;
        }

        const canvas = document.createElement('canvas');
        const scale = Math.min(1, 1280 / Math.max(video.videoWidth, video.videoHeight));
        canvas.width = Math.round(video.videoWidth * scale);
        canvas.height = Math.round(video.videoHeight * scale);
        canvas.getContext('2d').drawImage(video, 0, 0, canvas.width, canvas.height);

        file.value = '';
        webcam.value = canvas.toDataURL('image/jpeg', 0.9);
        preview.src = webcam.value;
        preview.hidden = false;
        remove.hidden = false;
        stop();
    });

    file.addEventListener('change', () => {
        clearPhoto();
        stop();
    });
    clearPhoto();
    close.addEventListener('click', stop);
    remove.addEventListener('click', clearPhoto);
    window.addEventListener('pageshow', () => { pageActive = true; });
    window.addEventListener('pagehide', () => {
        pageActive = false;
        stop();
    });
}
