import { Controller } from '@hotwired/stimulus';

/**
 * Records a question with the microphone, up to `maxSeconds`, then posts it to `url`
 * with the listener's name, and tells what the hosts heard.
 *
 * @target button  Starts the recording, then stops and sends it.
 * @target name    The listener's name, optional.
 * @target status  Where the listener reads what happens.
 */
export default class extends Controller {
    static targets = ['button', 'name', 'status'];
    static values = {
        url: String,
        maxSeconds: { type: Number, default: 60 },
    };

    #recorder;
    #timeout;

    async toggle() {
        if ('recording' === this.#recorder?.state) {
            this.#recorder.stop();

            return;
        }

        let stream;
        try {
            stream = await navigator.mediaDevices.getUserMedia({ audio: true });
        } catch {
            this.#say('The microphone is not available.');

            return;
        }

        // Safari only records mp4
        const mimeType = ['audio/webm', 'audio/mp4'].find((type) => MediaRecorder.isTypeSupported(type));
        const chunks = [];
        this.#recorder = new MediaRecorder(stream, mimeType ? { mimeType } : {});
        this.#recorder.addEventListener('dataavailable', (event) => chunks.push(event.data));
        this.#recorder.addEventListener('stop', () => {
            clearTimeout(this.#timeout);
            stream.getTracks().forEach((track) => track.stop());
            this.#send(new Blob(chunks, { type: this.#recorder.mimeType }));
        });
        this.#recorder.start();
        this.#timeout = setTimeout(() => this.#recorder.stop(), this.maxSecondsValue * 1000);

        this.buttonTarget.textContent = 'Stop and send';
        this.#say('Recording… ask your question.');
    }

    async #send(audio) {
        this.buttonTarget.disabled = true;
        this.buttonTarget.textContent = 'Record a question';
        this.#say('The hosts are listening…');

        const form = new FormData();
        form.append('audio', audio, audio.type.includes('mp4') ? 'question.mp4' : 'question.webm');
        form.append('name', this.nameTarget.value);

        try {
            const response = await fetch(this.urlValue, { method: 'POST', body: form });
            const data = await response.json();
            this.#say(response.ok ? `Thanks! The hosts heard: “${data.question}”` : (data.error ?? data.detail ?? 'Sorry, that did not work.'));
        } catch {
            this.#say('Sorry, that did not work.');
        }

        this.buttonTarget.disabled = false;
    }

    #say(text) {
        this.statusTarget.textContent = text;
    }
}
