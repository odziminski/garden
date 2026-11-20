const panel = document.querySelector('[data-browser-push]');

if (panel) {
    const status = panel.querySelector('[data-push-status]');
    const enable = panel.querySelector('[data-push-enable]');
    const disable = panel.querySelector('[data-push-disable]');
    const supported = window.isSecureContext && 'serviceWorker' in navigator
        && 'PushManager' in window && 'Notification' in window;

    if (supported) {
        enable.hidden = disable.hidden = false;
        enable.addEventListener('click', () => changeSubscription(true));
        disable.addEventListener('click', () => changeSubscription(false));
    } else {
        status.textContent = 'Browser reminders are unavailable on this device.';
    }

    async function changeSubscription(enabled) {
        enable.disabled = disable.disabled = true;
        status.textContent = '';
        try {
            if (enabled && await Notification.requestPermission() !== 'granted') {
                status.textContent = 'Allow notifications in your browser settings to continue.';
                return;
            }

            await navigator.serviceWorker.register(panel.dataset.worker);
            const registration = await navigator.serviceWorker.ready;
            let subscription = await registration.pushManager.getSubscription();
            if (!subscription && enabled) {
                const key = panel.dataset.publicKey.replace(/-/g, '+').replace(/_/g, '/');
                subscription = await registration.pushManager.subscribe({
                    userVisibleOnly: true,
                    applicationServerKey: Uint8Array.from(atob(key), (character) => character.charCodeAt(0)),
                });
            }

            if (subscription) {
                const response = await fetch(panel.dataset.url, {
                    method: enabled ? 'PUT' : 'DELETE',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                    body: JSON.stringify(enabled ? subscription.toJSON() : { endpoint: subscription.endpoint }),
                });
                if (!response.ok) {
                    const failure = await response.json();
                    throw new Error(failure.message || 'Could not update browser reminders.');
                }

                if (!enabled) {
                    await subscription.unsubscribe();
                }
            }

            status.textContent = enabled ? 'Reminders enabled on this device.' : 'Reminders disabled on this device.';
        } catch (error) {
            status.textContent = error.message || 'Could not update browser reminders. Try again.';
        } finally {
            enable.disabled = disable.disabled = false;
        }
    }
}
