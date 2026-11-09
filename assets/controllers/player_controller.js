import { Controller } from '@hotwired/stimulus';

/**
 * Plays the episode from a chapter.
 *
 * @target audio  The episode's <audio> element.
 * @action seek   Plays from the `start` param, in seconds.
 */
export default class extends Controller {
    static targets = ['audio'];

    seek({ params: { start } }) {
        this.audioTarget.currentTime = start;
        this.audioTarget.play();
    }
}
