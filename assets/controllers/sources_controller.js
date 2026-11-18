import { Controller } from '@hotwired/stimulus';

/**
 * @target  search  Checkbox: ticked, the hosts search the web for their sources themselves.
 * @target  links   Form row that only matters when they do not: hidden, and its fields left out of the form, while `search` is ticked.
 * @action  toggle  Shows or hides the `links` targets to match the `search` checkbox.
 */
export default class extends Controller {
    static targets = ['search', 'links'];

    connect() {
        this.toggle();
    }

    toggle() {
        const searching = this.hasSearchTarget && this.searchTarget.checked;

        this.linksTargets.forEach((row) => {
            row.hidden = searching;
            row.querySelectorAll('input, textarea').forEach((field) => (field.disabled = searching));
        });
    }
}
