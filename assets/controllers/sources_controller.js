import { Controller } from '@hotwired/stimulus';

/**
 * Where the facts of an episode come from: the hosts search the web, or the editor gives links or notes.
 *
 * @target  search   Checkbox: ticked, the hosts search the web for their sources themselves.
 * @target  links    Form row that only matters when they do not: hidden, and its fields left out of the form, while `search` is ticked.
 * @target  notes    The editor's own notes, a source like the others.
 * @value   message  What the browser says when the hosts would have no source at all.
 * @action  toggle   Shows or hides the `links` targets to match the `search` checkbox.
 * @action  check    Refuses, with `message`, a form that brings no source.
 */
export default class extends Controller {
    static targets = ['search', 'links', 'notes'];
    static values = { message: String };

    connect() {
        this.toggle();
    }

    toggle() {
        const searching = this.hasSearchTarget && this.searchTarget.checked;

        this.linksTargets.forEach((row) => {
            row.hidden = searching;
            row.querySelectorAll('input, textarea').forEach((field) => (field.disabled = searching));
        });
        this.check();
    }

    check() {
        const searching = this.hasSearchTarget && this.searchTarget.checked;
        const links = this.linksTargets.flatMap((row) => [...row.querySelectorAll('textarea')]);
        const given = [...links, ...this.notesTargets].some((field) => '' !== field.value.trim());

        links.forEach((field) => field.setCustomValidity(searching || given ? '' : this.messageValue));
    }
}
